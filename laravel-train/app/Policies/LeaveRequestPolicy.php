<?php

namespace App\Policies;

use App\Constants\AppConstants;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    // Kiểm tra quyền duyệt hoặc hủy đơn
    public function process(User $user, LeaveRequest $leave): bool
    {
        if ($user->status !== AppConstants::STATUS_ACTIVE || $leave->status !== AppConstants::STATUS_NEW) {
            return false;
        }
        if ($user->user_type === AppConstants::TYPE_ADMIN) {
            return true;
        }

        return $user->user_type === AppConstants::TYPE_MANAGER
            && $leave->approver_id === $user->id
            && $leave->user?->user_type === AppConstants::TYPE_USER
            && $leave->user?->department === $user->department;
    }

    // Kiểm tra quyền sửa đơn
    public function update(User $user, LeaveRequest $leave): bool
    {
        return $user->status === AppConstants::STATUS_ACTIVE
            && $leave->user_id === $user->id
            && $leave->status === AppConstants::STATUS_NEW;
    }
}
