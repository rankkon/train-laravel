<?php

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('processing permission follows the original role, assignment, owner and state rules', function (
    int $role, int $actorStatus, int $ownerRole, int $ownerDepartment, bool $assigned, int $status, bool $allowed
): void {
    $actor = User::factory()->make(['id' => 10, 'user_type' => $role, 'department' => 2, 'status' => $actorStatus]);
    $owner = User::factory()->locked()->make(['id' => 20, 'user_type' => $ownerRole, 'department' => $ownerDepartment]);
    $leave = LeaveRequest::factory()->make(['user_id' => 20, 'approver_id' => $assigned ? 10 : 99, 'status' => $status]);
    $leave->setRelation('user', $owner);

    expect(Gate::forUser($actor)->allows('process', $leave))->toBe($allowed);
})->with([
    'user cannot process assigned requests' => [1, 1, 1, 2, true, 0, false],
    'manager can process assigned user even when owner is locked' => [2, 1, 1, 2, true, 0, true],
    'manager needs assignment' => [2, 1, 1, 2, false, 0, false],
    'manager needs the owner department' => [2, 1, 1, 3, true, 0, false],
    'manager cannot process manager requests' => [2, 1, 2, 2, true, 0, false],
    'manager cannot process admin requests' => [2, 1, 3, 2, true, 0, false],
    'locked manager cannot process' => [2, 0, 1, 2, true, 0, false],
    'approved request cannot be processed' => [2, 1, 1, 2, true, 1, false],
    'cancelled request cannot be processed' => [2, 1, 1, 2, true, 2, false],
    'admin ignores department, assignment and owner role' => [3, 1, 3, 3, false, 0, true],
    'locked admin cannot process' => [3, 0, 1, 2, true, 0, false],
    'admin cannot process approved requests' => [3, 1, 1, 2, true, 1, false],
    'admin cannot process cancelled requests' => [3, 1, 1, 2, true, 2, false],
]);

test('editing permission belongs only to the active owner of a new request', function (
    int $role, int $actorStatus, bool $owns, int $status, bool $allowed
): void {
    $actor = User::factory()->make(['id' => 10, 'user_type' => $role, 'status' => $actorStatus]);
    $leave = LeaveRequest::factory()->make(['user_id' => $owns ? 10 : 20, 'approver_id' => 99, 'status' => $status]);

    expect(Gate::forUser($actor)->allows('update', $leave))->toBe($allowed);
})->with([
    'user owns new' => [1, 1, true, 0, true],
    'manager owns new' => [2, 1, true, 0, true],
    'admin owns new' => [3, 1, true, 0, true],
    'admin does not own it' => [3, 1, false, 0, false],
    'user does not own it' => [1, 1, false, 0, false],
    'locked owner' => [1, 0, true, 0, false],
    'approved request' => [1, 1, true, 1, false],
    'cancelled request' => [1, 1, true, 2, false],
]);
