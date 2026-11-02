<?php

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

beforeEach(function (): void {
    $this->createLegacySchema();
    $this->enableLeaveSearchFunctions();
    $this->app->setLocale('vi');
});

/** Tạo đơn và tài khoản dùng cho kiểm thử. */
function createProcessableLeave(int $role = User::TYPE_MANAGER): array
{
    $actor = User::factory()->create(['user_type' => $role, 'department' => 2]);
    $owner = User::factory()->create(['user_type' => User::TYPE_USER, 'department' => 2]);
    $leave = LeaveRequest::factory()->for($owner, 'user')->for($actor, 'approver')->create([
        'title' => 'Keep this request', 'created_at' => '2030-01-01 08:00:00',
    ]);

    return [$actor, $owner, $leave->fresh()];
}

test('opening either popup does not mutate the request or validate a cancellation reason', function (string $action, string $popup): void {
    [$actor, , $leave] = createProcessableLeave();
    $original = $leave->getRawOriginal();

    $response = $this->actingAs($actor)->post('/leaves/actions', [
        'action' => $action, 'leave_id' => (string) $leave->id, 'cancel_reason' => ['ignored'],
    ]);

    $response->assertOk()->assertViewIs('leaves.index')->assertViewHas('processPopup', $popup)
        ->assertViewHas('processLeaveId', $leave->id)->assertViewHas('processErrors', []);
    expect($leave->fresh()->getRawOriginal())->toBe($original);
})->with([['open_approve', 'approve'], ['open_cancel', 'cancel']]);

test('closing a popup ignores malformed fields and preserves the search and requested page', function (): void {
    [$actor, , $leave] = createProcessableLeave();
    $original = $leave->getRawOriginal();

    $response = $this->actingAs($actor)->post('/leaves/actions?q=%20keep%20&page=999', [
        'action' => 'close_popup', 'leave_id' => ['invalid'], 'cancel_reason' => ['invalid'],
    ]);

    $response->assertStatus(303)->assertRedirectToRoute('leaves.index', ['q' => 'keep', 'page' => 999]);
    expect($leave->fresh()->getRawOriginal())->toBe($original);
});

test('popup actions retain the requested page even while the displayed rows are clamped', function (): void {
    [$actor, , $leave] = createProcessableLeave();

    $response = $this->actingAs($actor)->post('/leaves/actions?q=keep&page=999', [
        'action' => 'open_approve', 'leave_id' => (string) $leave->id,
    ]);

    $response->assertOk()->assertViewHas('actionUrl', route('leaves.actions', ['q' => 'keep', 'page' => 999]));
    expect($response['leaves']->currentPage())->toBe(1);
});

test('the login, list, popup, cancellation and dashboard flow reflects the saved state', function (): void {
    [$actor, , $leave] = createProcessableLeave();
    $this->post('/login', ['username' => $actor->username, 'password' => 'Secret123!'])->assertRedirectToRoute('dashboard');
    Auth::forgetGuards();
    $this->get('/leaves')->assertOk()->assertSee('value="open_approve"', false);
    $this->post('/leaves/actions', ['action' => 'open_approve', 'leave_id' => (string) $leave->id])
        ->assertOk()->assertSee('Bạn có chắc chắn duyệt đơn này?');
    $this->post('/leaves/actions', ['action' => 'close_popup'])->assertStatus(303);
    $this->post('/leaves/actions', ['action' => 'open_cancel', 'leave_id' => (string) $leave->id])
        ->assertOk()->assertViewHas('processPopup', 'cancel');
    $this->post('/leaves/actions', ['action' => 'confirm_cancel', 'leave_id' => (string) $leave->id, 'cancel_reason' => ''])
        ->assertOk()->assertSee('※Vui lòng nhập lý do hủy đơn.');

    $response = $this->post('/leaves/actions', [
        'action' => 'confirm_cancel', 'leave_id' => (string) $leave->id, 'cancel_reason' => 'Confirmed reason',
    ]);

    $response->assertStatus(303)->assertRedirectToRoute('leaves.index', ['q' => '', 'page' => 1]);
    $this->get('/leaves')->assertOk()->assertSee('Đã hủy')->assertDontSee('value="open_approve"', false);
    $dashboard = $this->get('/dashboard')->assertOk()->assertSee('Đã hủy');
    expect($dashboard['leaves']->first()->status)->toBe(LeaveRequest::STATUS_CANCELLED);
    expect($leave->fresh()->cancel_reason)->toBe('Confirmed reason');
});

test('approval works without a previous popup and only changes approval fields', function (): void {
    [$actor, , $leave] = createProcessableLeave();
    $this->travelTo(new DateTimeImmutable('2030-03-10 13:14:15+07:00'));
    $original = $leave->getRawOriginal();

    $response = $this->actingAs($actor)->post('/leaves/actions?q=keep&page=999', [
        'action' => 'confirm_approve', 'leave_id' => (string) $leave->id,
        'status' => 2, 'approved_at' => '1990-01-01', 'approver_id' => 999999,
    ]);

    $response->assertStatus(303)->assertRedirectToRoute('leaves.index', ['q' => 'keep', 'page' => 999]);
    expect($leave->fresh()->getRawOriginal())->toBe(array_replace($original, [
        'status' => 1, 'update_at' => '2030-03-10 13:14:15', 'approved_at' => '2030-03-10',
    ]));
    expect(DB::transactionLevel())->toBe(0);
});

test('admin may approve their own request even if the original designated approver is missing', function (): void {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    $leave = LeaveRequest::factory()->for($admin, 'user')->create(['approver_id' => 999999]);

    $response = $this->actingAs($admin)->post('/leaves/actions', ['action' => 'confirm_approve', 'leave_id' => (string) $leave->id]);

    $response->assertStatus(303)->assertRedirectToRoute('leaves.index', ['q' => '', 'page' => 1]);
    expect($leave->fresh()->status)->toBe(LeaveRequest::STATUS_APPROVED);
});

test('cancellation trims and stores its reason and changes only cancellation fields', function (): void {
    [$actor, , $leave] = createProcessableLeave();
    $this->travelTo(new DateTimeImmutable('2030-03-10 13:14:15+07:00'));
    $original = $leave->getRawOriginal();

    $response = $this->actingAs($actor)->post('/leaves', [
        'action' => 'confirm_cancel', 'leave_id' => (string) $leave->id,
        'cancel_reason' => " \tKhông phù hợp\r\n", 'approved_at' => '1990-01-01',
    ]);

    $response->assertStatus(303)->assertRedirectToRoute('leaves.index', ['q' => '', 'page' => 1]);
    expect($leave->fresh()->getRawOriginal())->toBe(array_replace($original, [
        'status' => 2, 'delete_at' => '2030-03-10 13:14:15', 'cancel_reason' => 'Không phù hợp',
    ]));
});

test('cancellation errors preserve the popup, response status and unchanged record', function (
    mixed $reason, int $status, string $message
): void {
    [$actor, , $leave] = createProcessableLeave();
    $original = $leave->getRawOriginal();

    $response = $this->actingAs($actor)->post('/leaves/actions', [
        'action' => 'confirm_cancel', 'leave_id' => (string) $leave->id, 'cancel_reason' => $reason,
    ]);

    $response->assertStatus($status)->assertViewIs('leaves.index')->assertViewHas('processPopup', 'cancel')
        ->assertSee($message)->assertHeaderMissing('Location');
    expect($leave->fresh()->getRawOriginal())->toBe($original);
})->with([
    'empty' => ['', 200, '※Vui lòng nhập lý do hủy đơn.'],
    'missing' => [null, 200, '※Vui lòng nhập lý do hủy đơn.'],
    'Unicode whitespace' => [" \u{3000}\u{00A0}\t", 200, '※Vui lòng nhập lý do hủy đơn.'],
    'invalid UTF-8' => ["\xFF", 200, '※Lý do hủy phải là văn bản hợp lệ.'],
    'above byte limit' => [str_repeat('あ', 21846), 200, '※Lý do hủy vượt giới hạn lưu trữ.'],
    'array' => [['invalid'], 400, '※Giá trị gửi lên không hợp lệ.'],
]);

test('a cancellation reason at exactly the UTF-8 byte limit is accepted', function (): void {
    [$actor, , $leave] = createProcessableLeave();
    $reason = str_repeat('あ', 21845);

    $response = $this->actingAs($actor)->post('/leaves/actions', [
        'action' => 'confirm_cancel', 'leave_id' => (string) $leave->id, 'cancel_reason' => $reason,
    ]);

    $response->assertStatus(303);
    expect($leave->fresh()->cancel_reason)->toBe($reason);
    expect(strlen($leave->fresh()->cancel_reason))->toBe(65535);
});

test('invalid cancellation reasons are checked before record existence and permissions', function (): void {
    $actor = User::factory()->create();

    $this->actingAs($actor)->post('/leaves/actions', [
        'action' => 'confirm_cancel', 'leave_id' => '999999', 'cancel_reason' => '',
    ])->assertOk()->assertViewHas('processPopup', 'cancel')->assertSee('※Vui lòng nhập lý do hủy đơn.');
});

test('bad action and identifier payloads use the original common bad-request error', function (array $payload): void {
    [$actor, , $leave] = createProcessableLeave();
    $original = $leave->getRawOriginal();

    $response = $this->actingAs($actor)->post('/leaves/actions', $payload);

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => 400]);
    expect($leave->fresh()->getRawOriginal())->toBe($original);
})->with([
    'absent action' => [[]],
    'unknown action' => [['action' => 'delete', 'leave_id' => '1']],
    'action array' => [['action' => ['open_approve'], 'leave_id' => '1']],
    'whitespace action must not be normalized' => [['action' => ' open_approve ', 'leave_id' => '1']],
    'missing id' => [['action' => 'open_approve']],
    'id array' => [['action' => 'open_approve', 'leave_id' => ['1']]],
    'integer instead of submitted string' => [['action' => 'open_approve', 'leave_id' => 1]],
    'zero' => [['action' => 'open_approve', 'leave_id' => '0']],
    'SQL syntax' => [['action' => 'open_approve', 'leave_id' => '1 OR 1=1']],
]);

test('opening a forbidden popup is 403 while confirming a forbidden operation is 409', function (string $action, int $code): void {
    [$actor, , $leave] = createProcessableLeave(User::TYPE_USER);
    $original = $leave->getRawOriginal();

    $response = $this->actingAs($actor)->post('/leaves/actions', [
        'action' => $action, 'leave_id' => (string) $leave->id, 'cancel_reason' => 'Valid reason',
    ]);

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => $code]);
    expect($leave->fresh()->getRawOriginal())->toBe($original);
})->with([['open_approve', 403], ['open_cancel', 403], ['confirm_approve', 409], ['confirm_cancel', 409]]);

test('missing requests or missing owners use the not-found error in either processing step', function (bool $missingOwner, string $action): void {
    [$actor, $owner, $leave] = createProcessableLeave();
    if ($missingOwner) {
        $owner->delete();
    } else {
        $leave->delete();
    }

    $response = $this->actingAs($actor)->post('/leaves/actions', ['action' => $action, 'leave_id' => (string) $leave->id]);

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => 404]);
    expect(DB::transactionLevel())->toBe(0);
})->with([[false, 'open_approve'], [true, 'open_approve'], [false, 'confirm_approve'], [true, 'confirm_approve']]);

test('confirm rechecks record state, assignment and owner department after opening the popup', function (string $change): void {
    [$actor, $owner, $leave] = createProcessableLeave();
    $this->actingAs($actor)->post('/leaves/actions', ['action' => 'open_approve', 'leave_id' => (string) $leave->id])->assertOk();
    match ($change) {
        'state' => LeaveRequest::query()->whereKey($leave->id)->update(['status' => 1]),
        'assignment' => LeaveRequest::query()->whereKey($leave->id)->update(['approver_id' => 999999]),
        'department' => User::query()->whereKey($owner->id)->update(['department' => 3]),
        'owner role' => User::query()->whereKey($owner->id)->update(['user_type' => User::TYPE_MANAGER]),
        'actor role' => User::query()->whereKey($actor->id)->update(['user_type' => User::TYPE_USER]),
    };
    $original = $leave->fresh()->getRawOriginal();

    $response = $this->post('/leaves/actions', ['action' => 'confirm_approve', 'leave_id' => (string) $leave->id]);

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => 409]);
    expect($leave->fresh()->getRawOriginal())->toBe($original);
})->with(['state', 'assignment', 'department', 'owner role', 'actor role']);

test('an account locked after middleware loses authentication at the locked-user recheck', function (): void {
    [$actor, , $leave] = createProcessableLeave();
    $this->actingAs($actor);
    User::query()->whereKey($actor->id)->update(['status' => User::STATUS_LOCKED]);

    $response = $this->withSession(['draft_marker' => 'keep'])->post('/leaves/actions', [
        'action' => 'confirm_approve', 'leave_id' => (string) $leave->id,
    ]);

    $response->assertStatus(303)->assertRedirectToRoute('login')->assertSessionHas('draft_marker', 'keep');
    $this->assertGuest();
    expect($leave->fresh()->status)->toBe(LeaveRequest::STATUS_NEW);
    expect(DB::transactionLevel())->toBe(0);
});

test('a database write failure rolls back and uses the common error screen', function (): void {
    [$actor, , $leave] = createProcessableLeave();
    $original = $leave->getRawOriginal();
    DB::unprepared("CREATE TRIGGER fail_leave_update BEFORE UPDATE ON `leave` BEGIN SELECT RAISE(ABORT, 'blocked update'); END");
    Exceptions::fake();

    $response = $this->actingAs($actor)->post('/leaves/actions', ['action' => 'confirm_approve', 'leave_id' => (string) $leave->id]);

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => 500]);
    Exceptions::assertReported(QueryException::class);
    expect($leave->fresh()->getRawOriginal())->toBe($original);
    expect(DB::transactionLevel())->toBe(0);
});
