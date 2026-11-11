<?php

namespace App\Constants;

class AppConstants
{
    public const TYPE_USER        = 1; // Mã vai trò: Người dùng
    public const TYPE_MANAGER     = 2; // Mã vai trò: Quản lý
    public const TYPE_ADMIN       = 3; // Mã vai trò: Quản trị viên
    public const STATUS_ACTIVE    = 1; // Mã trạng thái: Hoạt động
    public const STATUS_LOCKED    = 0; // Mã trạng thái: Bị khóa
    public const USER_CODE_LENGTH = 4; // Độ dài của mã người dùng

    // Các trạng thái của người dùng
    public const USER_STATUSES = [
        self::STATUS_LOCKED => 'Đã khóa',
        self::STATUS_ACTIVE => 'Đang hoạt động',
    ];

    // Danh mục loại người dùng
    public const USER_TYPES = [
        self::TYPE_USER    => 'User',
        self::TYPE_MANAGER => 'Manager',
        self::TYPE_ADMIN   => 'Admin',
    ];

    // Danh mục mã và tên phòng ban
    public const DEPARTMENTS = [
        1 => 'Ban quản lý',
        2 => 'Nhân sự',
        3 => 'Kế toán',
        4 => 'Kỹ thuật',
        5 => 'Sản xuất',
    ];

    public const STATUS_NEW                 = 0; // Trạng thái: Mới tạo
    public const STATUS_APPROVED            = 1;  // Trạng thái: Đã duyệt
    public const STATUS_CANCELLED           = 2; // Trạng thái: Đã hủy
    public const TYPE_NGHIPHEP              = 1; // Mã loại đơn: Nghỉ phép
    public const TYPE_VATTU                 = 2; // Mã loại đơn: Vật tư
    public const TYPE_GIOLAM                = 3; // Mã loại đơn: Giờ làm
    public const TYPE_CHIPHICONGTAC         = 4; // Mã loại đơn: Chi phí công tác

    // Các loại đơn
    public const LEAVE_TYPES = [
        self::TYPE_NGHIPHEP      => 'Đơn nghỉ phép',
        self::TYPE_VATTU         => 'Đơn cấp vật tư máy móc',
        self::TYPE_GIOLAM        => 'Đơn thay đổi giờ làm',
        self::TYPE_CHIPHICONGTAC => 'Đơn xin thanh toán công tác phí',
    ];

    // Các trạng thái đơn
    public const LEAVE_STATUSES = [
        self::STATUS_NEW       => 'Đơn mới',
        self::STATUS_APPROVED  => 'Đã duyệt',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];
}
