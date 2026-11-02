<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LeaveService
{
    /** Duyệt hoặc hủy đơn trong transaction. */
    public function process(int $userId, int $leaveId, string $action, string $reason): void
    {
        DB::transaction(function () use ($userId, $leaveId, $action, $reason): void {
            $leave = LeaveRequest::query()
                ->whereKey($leaveId)
                ->lockForUpdate()
                ->first();
            abort_if($leave === null, 404);

            $users = User::query()
                ->whereIn('id', [$userId, $leave->user_id, $leave->approver_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $currentUser = $users->get($userId);
            if ($currentUser === null || $currentUser->status !== User::STATUS_ACTIVE) {
                throw new AuthenticationException;
            }

            $owner = $users->get($leave->user_id);
            abort_if($owner === null, 404);
            $leave->setRelation('user', $owner);
            abort_unless(Gate::forUser($currentUser)->allows('process', $leave), 409);

            $processedAt = now();
            if ($action === 'approve') {
                $changes = [
                    'status' => LeaveRequest::STATUS_APPROVED,
                    'update_at' => $processedAt->format('Y-m-d H:i:s'),
                    'approved_at' => $processedAt->toDateString(),
                ];
            } else {
                $changes = [
                    'status' => LeaveRequest::STATUS_CANCELLED,
                    'delete_at' => $processedAt->format('Y-m-d H:i:s'),
                    'cancel_reason' => $reason,
                ];
            }

            LeaveRequest::query()
                ->whereKey($leaveId)
                ->where('status', LeaveRequest::STATUS_NEW)
                ->update($changes);
        });
    }
}
