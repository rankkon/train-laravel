<?php

use App\Models\LeaveRequest;
use App\Models\User;

beforeEach(function (): void {
    $this->createLegacySchema();
});

test('requests resolve their creator and designated approver through the original keys', function (): void {
    $creator = User::factory()->create();
    $approver = User::factory()->create(['user_type' => User::TYPE_MANAGER]);
    $unrelated = User::factory()->create();
    $leave = LeaveRequest::factory()->for($creator, 'user')->for($approver, 'approver')->create();
    $otherLeave = LeaveRequest::factory()->for($unrelated, 'user')->for($unrelated, 'approver')->create();

    $loaded = LeaveRequest::query()->with(['user', 'approver'])->findOrFail($leave->id);

    expect($loaded->user->is($creator))->toBeTrue();
    expect($loaded->approver->is($approver))->toBeTrue();
    expect($creator->leaveRequests()->pluck('id')->all())->toBe([$leave->id]);
    expect($approver->assignedLeaveRequests()->pluck('id')->all())->toBe([$leave->id]);
    expect($creator->assignedLeaveRequests()->exists())->toBeFalse();
    expect($approver->leaveRequests()->exists())->toBeFalse();
    expect($unrelated->leaveRequests()->pluck('id')->all())->toBe([$otherLeave->id]);
});

test('editing request content preserves approval and cancellation timestamps', function (): void {
    $leave = LeaveRequest::factory()->create([
        'created_at' => '2030-03-01 09:10:11',
        'start_date' => '2030-03-02',
        'end_date' => '2030-03-04',
    ]);

    $leave->title = 'Changed title';
    $leave->save();
    $saved = $leave->fresh();

    expect($saved->title)->toBe('Changed title');
    expect($saved->created_at->format('Y-m-d H:i:s'))->toBe('2030-03-01 09:10:11');
    expect($saved->update_at)->toBeNull();
    expect($saved->approved_at)->toBeNull();
    expect($saved->delete_at)->toBeNull();
    $this->assertDatabaseHas('leave', ['id' => $leave->id, 'title' => 'Changed title']);
});

test('stored request dates retain their local values and nullable historical dates', function (): void {
    $leave = LeaveRequest::factory()->create([
        'start_date' => '2030-03-02',
        'end_date' => '2030-03-04',
        'status' => LeaveRequest::STATUS_APPROVED,
        'approved_at' => '2030-03-01',
        'update_at' => '2030-03-01 09:10:11',
    ]);

    $saved = $leave->fresh();
    $serialized = $saved->toArray();

    expect($serialized['start_date'])->toBe('2030-03-02');
    expect($serialized['end_date'])->toBe('2030-03-04');
    expect($serialized['approved_at'])->toBe('2030-03-01');
    expect($serialized['update_at'])->toBe('2030-03-01 09:10:11');
    expect($saved->update_at->toIso8601String())->toBe('2030-03-01T09:10:11+07:00');
    expect($serialized['delete_at'])->toBeNull();
});

test('users retain local dates and hide the legacy password hash when serialized', function (): void {
    $user = User::factory()->create([
        'birth_date' => '1995-04-05',
        'created_at' => '2030-03-01 09:10:11',
        'update_at' => null,
    ]);

    $serialized = $user->fresh()->toArray();

    expect($serialized['birth_date'])->toBe('1995-04-05');
    expect($serialized['created_at'])->toBe('2030-03-01 09:10:11');
    expect($serialized['update_at'])->toBeNull();
    expect($serialized)->not->toHaveKey('password_hash');
});
