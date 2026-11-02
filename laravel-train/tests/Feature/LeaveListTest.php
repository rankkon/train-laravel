<?php

use App\Models\LeaveRequest;
use App\Models\User;

beforeEach(function (): void {
    $this->createLegacySchema();
    $this->enableLeaveSearchFunctions();
    $this->app->setLocale('vi');
});

test('guests cannot list or process requests', function (): void {
    $this->get('/leaves')->assertRedirectToRoute('login');
    $this->post('/leaves/actions', ['action' => 'confirm_approve', 'leave_id' => '1'])->assertRedirectToRoute('login');
});

test('list scope is applied before counting and pagination for each role', function (int $role, int $count): void {
    $actor = User::factory()->create(['user_type' => $role, 'department' => 2]);
    $colleague = User::factory()->locked()->create(['department' => 2]);
    $outsider = User::factory()->create(['department' => 3]);
    $factory = LeaveRequest::factory()->for($actor, 'approver');
    $factory->for($colleague, 'user')->create(['title' => 'Visible locked owner']);
    $factory->for($outsider, 'user')->count(16)->create(['title' => 'Outside department']);
    $factory->create(['user_id' => 999999, 'title' => 'Orphan request']);

    $response = $this->actingAs($actor)->get('/leaves?department=3&user_type=3');

    $response->assertOk()->assertViewIs('leaves.index')->assertDontSee('Orphan request');
    expect($response['leaves']->total())->toBe($count);
})->with(['user' => [1, 1], 'manager' => [2, 1], 'admin' => [3, 17]]);

test('list orders status first and then creation time and id descending', function (): void {
    $actor = User::factory()->create();
    $factory = LeaveRequest::factory()->for($actor, 'user')->for($actor, 'approver');
    $cancelled = $factory->create(['status' => 2, 'created_at' => '2030-05-01 09:00:00']);
    $approved = $factory->create(['status' => 1, 'created_at' => '2030-04-01 09:00:00']);
    $olderNew = $factory->create(['status' => 0, 'created_at' => '2030-01-01 09:00:00']);
    $newer = $factory->create(['status' => 0, 'created_at' => '2030-02-01 09:00:00']);
    $tie = $factory->create(['status' => 0, 'created_at' => '2030-02-01 09:00:00']);

    $response = $this->actingAs($actor)->get('/leaves');

    $response->assertOk();
    expect($response['leaves']->pluck('id')->all())->toBe([$tie->id, $newer->id, $olderNew->id, $approved->id, $cancelled->id]);
});

test('list clamps invalid pages and keeps continuous row numbering', function (string $query, int $page, int $rows): void {
    $actor = User::factory()->create();
    LeaveRequest::factory()->count(31)->for($actor, 'user')->for($actor, 'approver')->create();

    $response = $this->actingAs($actor)->get('/leaves'.$query);

    $response->assertOk()->assertSee('<td>'.(($page - 1) * 15 + 1).'</td>', false);
    expect($response['leaves']->currentPage())->toBe($page);
    expect($response['leaves']->count())->toBe($rows);
    expect($response['leaves']->total())->toBe(31);
})->with([
    'first' => ['', 1, 15], 'invalid' => ['?page=bad', 1, 15],
    'array' => ['?page[]=2', 1, 15], 'negative' => ['?page=-2', 1, 15],
    'fraction' => ['?page=2.5', 1, 15], 'second' => ['?page=2', 2, 15],
    'above last' => ['?page=999', 3, 1],
]);

test('search matches owners, titles and type labels without leaking departments or expanding literal wildcards', function (
    string $search, array $expectedTitles
): void {
    $actor = User::factory()->create(['full_name' => 'Neutral owner', 'department' => 2]);
    $namedOwner = User::factory()->create(['full_name' => 'Needle owner', 'department' => 2]);
    $outsider = User::factory()->create(['department' => 3]);
    $factory = LeaveRequest::factory()->for($actor, 'approver')->state(['created_at' => '2030-01-01 09:00:00']);
    $factory->for($namedOwner, 'user')->create(['title' => 'Owner match', 'leave_type' => 1]);
    $factory->for($actor, 'user')->create(['title' => 'Needle title', 'leave_type' => 1]);
    $factory->for($actor, 'user')->create(['title' => 'Type match', 'leave_type' => 2]);
    $factory->for($actor, 'user')->create(['title' => 'Other title', 'content' => 'Needle only in content', 'leave_type' => 4]);
    $factory->for($actor, 'user')->create(['title' => '100%_! literal', 'leave_type' => 4]);
    $factory->for($actor, 'user')->create(['title' => "Cafe\u{0301}", 'leave_type' => 3]);
    $factory->for($outsider, 'user')->create(['title' => 'Needle outside', 'leave_type' => 2]);

    $response = $this->actingAs($actor)->get('/leaves?'.http_build_query(['q' => $search]));

    $response->assertOk()->assertViewHas('search', trim($search));
    expect($response['leaves']->pluck('title')->all())->toBe($expectedTitles);
    expect($response['leaves']->total())->toBe(count($expectedTitles));
})->with([
    'owner and title, not content' => ['  needle  ', ['Needle title', 'Owner match']],
    'ASCII case' => ['NEEDLE', ['Needle title', 'Owner match']],
    'type label' => ['máy móc', ['Type match']],
    'escaped wildcard characters' => ['%_!', ['100%_! literal']],
    'combining marks' => ['Cafe', ["Cafe\u{0301}"]],
    'SQL syntax' => ["%' OR 1=1 --", []],
]);

test('non-string search is ignored and escaped input remains safe in the search field and rows', function (): void {
    $actor = User::factory()->create(['full_name' => '<script>owner()</script>']);
    LeaveRequest::factory()->for($actor, 'user')->for($actor, 'approver')->create(['title' => '<script>title()</script>']);

    $this->actingAs($actor)->get('/leaves?q[]=invalid')->assertOk()->assertViewHas('search', '')
        ->assertSee($actor->full_name)->assertDontSee($actor->full_name, false);
    $this->get('/leaves?'.http_build_query(['q' => '<script>']))->assertOk()
        ->assertSee('<script>title()</script>')->assertDontSee('<script>title()</script>', false);
});

test('the list renders the applicable action buttons through the policy', function (int $role, bool $owns, bool $process, bool $edit): void {
    $actor = User::factory()->create(['user_type' => $role]);
    $owner = $owns ? $actor : User::factory()->create();
    $leave = LeaveRequest::factory()->for($owner, 'user')->for($actor, 'approver')->create();

    $response = $this->actingAs($actor)->get('/leaves');

    $response->assertOk()->assertSee('Hành động')->assertSee('Thêm mới đơn')->assertDontSee('name="_token"', false);
    expect(str_contains($response->getContent(), 'value="open_approve"'))->toBe($process);
    expect(str_contains($response->getContent(), 'value="open_cancel"'))->toBe($process);
    expect(str_contains($response->getContent(), 'title="Sửa"'))->toBe($edit);
})->with(['user owns it' => [1, true, false, true], 'assigned manager' => [2, false, true, false], 'admin owns it' => [3, true, true, true]]);

test('an empty list keeps the original message and its eight-column table', function (): void {
    $actor = User::factory()->create();

    $this->actingAs($actor)->get('/leaves?page=999')->assertOk()
        ->assertSee('<td colspan="8">Không có đơn phù hợp.</td>', false)
        ->assertSee('class="leave-page-current">1</span>', false);
});

test('unsupported list methods use the common method-not-allowed screen', function (): void {
    $actor = User::factory()->create();

    $this->actingAs($actor)->put('/leaves')->assertStatus(303)->assertRedirectToRoute('error', ['code' => 405]);
});
