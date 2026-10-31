<?php

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->createLegacySchema();
    $this->app->setLocale('vi');
});

test('dashboard limits visible owners by the current role and department before counting', function (
    int $role, int $expectedCount
): void {
    $viewer = User::factory()->create(['user_type' => $role, 'department' => 2]);
    $colleague = User::factory()->create(['user_type' => User::TYPE_MANAGER, 'department' => 2]);
    $outsider = User::factory()->create(['department' => 3]);
    $locked = User::factory()->locked()->create(['department' => 2]);
    $factory = LeaveRequest::factory()->for($outsider, 'approver')->state(['created_at' => '2030-01-01 09:00:00']);
    $ownLeave = $factory->for($viewer, 'user')->create(['title' => 'Own request']);
    $colleagueLeave = $factory->for($colleague, 'user')->create(['title' => 'Colleague request']);
    $outsideLeave = $factory->for($outsider, 'user')->create(['title' => 'Other department request']);
    $lockedLeave = $factory->for($locked, 'user')->create(['title' => 'Locked owner request']);
    $factory->create(['user_id' => 999999, 'title' => 'Orphan request']);
    $expectedIds = $role === User::TYPE_ADMIN
        ? [$lockedLeave->id, $outsideLeave->id, $colleagueLeave->id, $ownLeave->id]
        : [$lockedLeave->id, $colleagueLeave->id, $ownLeave->id];

    $response = $this->actingAs($viewer)->get('/dashboard?department=3&user_type=3&q=not-a-filter');

    $response->assertOk()->assertViewIs('dashboard')
        ->assertSee('Locked owner request')->assertSee('Colleague request')->assertDontSee('Orphan request');
    expect($response['leaves']->total())->toBe($expectedCount);
    expect($response['leaves']->pluck('id')->all())->toBe($expectedIds);
})->with([
    'User sees all owners in their department' => [User::TYPE_USER, 3],
    'Manager sees all owners in their department, not only assigned requests' => [User::TYPE_MANAGER, 3],
    'Admin sees every department but not orphaned requests' => [User::TYPE_ADMIN, 4],
]);

test('dashboard uses the latest department of the logged in account', function (): void {
    $viewer = User::factory()->create(['department' => 2]);
    $oldOwner = User::factory()->create(['department' => 2]);
    $otherOwner = User::factory()->create(['department' => 3]);
    LeaveRequest::factory()->for($otherOwner, 'approver')->for($oldOwner, 'user')->create(['title' => 'Old department']);
    LeaveRequest::factory()->for($viewer, 'approver')->for($otherOwner, 'user')->create(['title' => 'New department']);
    $this->post('/login', ['username' => $viewer->username, 'password' => 'Secret123!']);
    User::query()->whereKey($viewer->id)->update(['department' => 3]);
    Auth::forgetGuards();

    $response = $this->get('/dashboard');

    $response->assertOk()->assertSee('New department')->assertDontSee('Old department');
    expect($response['leaves']->total())->toBe(1);
});

test('dashboard orders by creation time then id rather than by status', function (): void {
    $viewer = User::factory()->create();
    $factory = LeaveRequest::factory()->for($viewer, 'user')->for($viewer, 'approver');
    $old = $factory->create(['created_at' => '2030-01-01 08:00:00', 'status' => LeaveRequest::STATUS_NEW]);
    $new = $factory->create(['created_at' => '2030-01-02 08:00:00', 'status' => LeaveRequest::STATUS_APPROVED]);
    $tie = $factory->create(['created_at' => '2030-01-02 08:00:00', 'status' => LeaveRequest::STATUS_CANCELLED]);

    $response = $this->actingAs($viewer)->get('/dashboard');

    $response->assertOk();
    expect($response['leaves']->pluck('id')->all())->toBe([$tie->id, $new->id, $old->id]);
});

test('dashboard preserves labels, raw dates, status classes and escaped user content', function (
    int $type, string $typeName, int $status, string $statusName
): void {
    $viewer = User::factory()->create(['full_name' => '<script>owner()</script>']);
    LeaveRequest::factory()->for($viewer, 'user')->for($viewer, 'approver')->create([
        'leave_type' => $type,
        'status' => $status,
        'title' => '<img src=x onerror=alert(1)>',
        'created_at' => '2030-03-01 23:45:12',
        'end_date' => '2030-03-04',
    ]);

    $response = $this->actingAs($viewer)->get('/dashboard');

    $response->assertOk()->assertSee($typeName)->assertSee($statusName)
        ->assertSee('class="leave-row leave-status-'.$status.'"', false)
        ->assertSee('<td>2030-03-01</td>', false)->assertSee('<td>2030-03-04</td>', false)
        ->assertSee($viewer->full_name)->assertDontSee($viewer->full_name, false)
        ->assertSee('<img src=x onerror=alert(1)>')->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertSee('css/leave_list.css')->assertDontSee('class="leave-actions"', false)
        ->assertDontSee('name="_token"', false);
})->with([
    'time off and new' => [LeaveRequest::TYPE_NGHIPHEP, 'Đơn nghỉ phép', 0, 'Đơn mới'],
    'supplies and approved' => [LeaveRequest::TYPE_VATTU, 'Đơn cấp vật tư máy móc', 1, 'Đã duyệt'],
    'work hours and cancelled' => [LeaveRequest::TYPE_GIOLAM, 'Đơn thay đổi giờ làm', 2, 'Đã hủy'],
    'travel expenses' => [LeaveRequest::TYPE_CHIPHICONGTAC, 'Đơn xin thanh toán công tác phí', 0, 'Đơn mới'],
]);

test('dashboard clamps pages and keeps fifteen rows and continuous row numbers', function (
    string $query, int $expectedPage, int $expectedRows, int $firstNumber, int $lastNumber
): void {
    $viewer = User::factory()->create();
    LeaveRequest::factory()->count(46)->for($viewer, 'user')->for($viewer, 'approver')
        ->create(['created_at' => '2030-01-01 09:00:00']);

    $response = $this->actingAs($viewer)->get('/dashboard'.$query);

    $response->assertOk()->assertSee('<td>'.$firstNumber.'</td>', false)->assertSee('<td>'.$lastNumber.'</td>', false);
    expect($response['leaves']->currentPage())->toBe($expectedPage);
    expect($response['leaves']->perPage())->toBe(15);
    expect($response['leaves']->total())->toBe(46);
    expect($response['leaves']->lastPage())->toBe(4);
    expect($response['leaves']->count())->toBe($expectedRows);
})->with([
    'absent' => ['', 1, 15, 1, 15],
    'blank' => ['?page=', 1, 15, 1, 15],
    'zero' => ['?page=0', 1, 15, 1, 15],
    'negative' => ['?page=-2', 1, 15, 1, 15],
    'text' => ['?page=invalid', 1, 15, 1, 15],
    'SQL syntax' => ['?page=1%20OR%201=1', 1, 15, 1, 15],
    'array' => ['?page[]=2', 1, 15, 1, 15],
    'fraction' => ['?page=2.5', 1, 15, 1, 15],
    'leading zero' => ['?page=02', 1, 15, 1, 15],
    'integer overflow' => ['?page=999999999999999999999999', 1, 15, 1, 15],
    'second' => ['?page=2', 2, 15, 16, 30],
    'last' => ['?page=4', 4, 1, 46, 46],
    'above last' => ['?page=999', 4, 1, 46, 46],
]);

test('pagination preserves the old nearby window and gaps even at either end', function (
    int $page, array $expectedNumbers, int $gaps
): void {
    $viewer = User::factory()->create();
    LeaveRequest::factory()->count(151)->for($viewer, 'user')->for($viewer, 'approver')
        ->create(['created_at' => '2030-01-01 09:00:00']);

    $response = $this->actingAs($viewer)->get('/dashboard?page='.$page);

    $response->assertOk()->assertSee('q=&amp;page=', false);
    preg_match('/<nav class="leave-pagination">(.*?)<\/nav>/s', $response->getContent(), $nav);
    preg_match_all('/>(\d+)</', $nav[1], $numbers);
    expect(array_map('intval', $numbers[1]))->toBe($expectedNumbers);
    expect(substr_count($nav[1], 'class="leave-page-gap"'))->toBe($gaps);
    $response->assertSee('class="leave-page-current">'.$page.'</span>', false);
})->with([
    'first page' => [1, [1, 2, 3, 11], 1],
    'middle page' => [6, [1, 4, 5, 6, 7, 8, 11], 2],
    'last page' => [11, [1, 9, 10, 11], 1],
]);

test('an empty dashboard keeps a single page and the original empty message', function (): void {
    $viewer = User::factory()->create();

    $response = $this->actingAs($viewer)->get('/dashboard?page=999');

    $response->assertOk()->assertSee('Không có đơn phù hợp.')
        ->assertSee('class="leave-page-current">1</span>', false)
        ->assertSee('class="leave-page-previous leave-page-disabled"', false)
        ->assertSee('class="leave-page-next leave-page-disabled"', false);
    expect($response['leaves']->currentPage())->toBe(1);
    expect($response['leaves']->total())->toBe(0);
});

test('dashboard sidebar keeps its active item, logout popup and the approved future links', function (): void {
    $viewer = User::factory()->create();

    $response = $this->actingAs($viewer)->get('/dashboard');

    $response->assertOk()->assertSee('class="menu-item menu-active"', false)
        ->assertSee('href="'.url('/users').'"', false)->assertSee('href="'.url('/leaves').'"', false)
        ->assertSee('Bạn có chắc chắn muốn đăng xuất?')->assertSee('Cancel')
        ->assertSee('method="post" action="'.route('logout').'"', false);
    $this->get('/users')->assertStatus(303)->assertRedirectToRoute('error', ['code' => 404]);
    $this->get('/leaves')->assertStatus(303)->assertRedirectToRoute('error', ['code' => 404]);
});

test('dashboard query failure returns the original error redirect and resets draft confirmations', function (): void {
    $viewer = User::factory()->create();
    Schema::drop('leave');
    Exceptions::fake();

    $response = $this->actingAs($viewer)->withSession([
        'user_create_draft' => ['confirmed' => true, 'full_name' => 'Keep user draft'],
        'user_create_save_pending' => true,
        'leave_create_draft' => ['confirmed' => true, 'title' => 'Keep leave draft'],
        'leave_create_save_pending' => true,
    ])->get('/dashboard');

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => 500])
        ->assertSessionHas('user_create_resume', true)->assertSessionHas('leave_create_resume', true)
        ->assertSessionHas('user_create_draft.confirmed', false)->assertSessionHas('leave_create_draft.confirmed', false)
        ->assertSessionHas('user_create_draft.full_name', 'Keep user draft')
        ->assertSessionHas('leave_create_draft.title', 'Keep leave draft')
        ->assertSessionMissing('user_create_save_pending')->assertSessionMissing('leave_create_save_pending');
    Exceptions::assertReported(QueryException::class);
});

test('the shared error page works without any database access', function (): void {
    $guardName = Auth::guard()->getName();
    $this->withSession([$guardName => 999999]);
    DB::purge();
    config(['database.connections.sqlite.database' => '/nonexistent/dashboard-error.sqlite']);
    Auth::forgetGuards();

    $response = $this->get('/error?code=500');

    $response->assertStatus(500)->assertViewIs('error')->assertSee('Có lỗi xảy ra')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('the error page accepts only the exact error codes from the original application', function (
    string $query, int $expectedStatus
): void {
    $response = $this->get('/error'.$query);

    $response->assertStatus($expectedStatus)->assertViewIs('error')->assertSee('Có lỗi xảy ra');
})->with([
    'bad request' => ['?code=400', 400],
    'forbidden' => ['?code=403', 403],
    'not found' => ['?code=404', 404],
    'method not allowed' => ['?code=405', 405],
    'conflict' => ['?code=409', 409],
    'server error' => ['?code=500', 500],
    'missing' => ['', 500],
    'unsupported' => ['?code=401', 500],
    'whitespace' => ['?code=%20404%20', 500],
    'array' => ['?code[]=404', 500],
]);

test('method-not-allowed error keeps the original allowed-methods header', function (): void {
    $this->get('/error?code=405')->assertStatus(405)->assertHeader('Allow', 'GET, HEAD, POST');
});
