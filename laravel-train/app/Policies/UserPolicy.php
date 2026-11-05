<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // Xác thực quyền tạo người dùng
    public function create(User $user): bool
    {
        return $user->status === User::STATUS_ACTIVE
            && in_array($user->user_type, [User::TYPE_MANAGER, User::TYPE_ADMIN], true); // Chỉ Manager và Admin đang hoạt động được thêm người dùng mới
    }

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
