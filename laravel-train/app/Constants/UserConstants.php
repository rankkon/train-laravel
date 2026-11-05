<?php

namespace App\Constants;

use App\Models\User;

class UserConstants
{
    public const USERS_PER_PAGE = 15; // Số lượng người dùng hiển thị trên mỗi trang

    public const USER_CODE_LENGTH = 4; // Độ dài của mã người dùng

    public const USER_STATUSES = [ // Các trạng thái của người dùng
        User::STATUS_LOCKED => 'Đã khóa',
        User::STATUS_ACTIVE => 'Đang hoạt động',
    ];

    // Danh mục loại người dùng
    public const USER_TYPES = [
        User::TYPE_USER    => 'User',
        User::TYPE_MANAGER => 'Manager',
        User::TYPE_ADMIN   => 'Admin',
    ];

    // Danh mục mã và tên phòng ban
    public const DEPARTMENTS = [
        1 => 'Ban quản lý',
        2 => 'Nhân sự',
        3 => 'Kế toán',
        4 => 'Kỹ thuật',
        5 => 'Sản xuất',
    ];
}
