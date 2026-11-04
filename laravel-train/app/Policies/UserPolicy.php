<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // Xác thực quyền quản lý người dùng
    public function manage(User $user, User $targetUser): bool
    {
        if ($user->status !== User::STATUS_ACTIVE) {
            return false;
        }
        // Admin có thể quản lý tất cả người dùng
        if ($user->user_type === User::TYPE_ADMIN) {
            return true;
        }

        // Manager chỉ có thể quản lý người dùng thuộc cùng phòng ban
        return $user->user_type === User::TYPE_MANAGER
            && $targetUser->user_type === User::TYPE_USER
            && $targetUser->department === $user->department;
    }
}
