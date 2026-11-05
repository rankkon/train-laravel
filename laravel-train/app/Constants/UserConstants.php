<?php

namespace App\Constants;

use App\Models\User;

class UserConstants
{
    public const USERNAME_MAX_LENGTH = 50; // Giới hạn độ dài tên đăng nhập

    public const USER_FULL_NAME_MAX_LENGTH = 100; // Giới hạn độ dài tên người dùng

    public const USER_PASSWORD_MIN_LENGTH = 8; // Độ dài mật khẩu tối thiểu

    public const USER_EMAIL_MAX_LENGTH = 254; // Giới hạn độ dài email

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
