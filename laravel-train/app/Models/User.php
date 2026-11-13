<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Hidden(['password_hash'])] // Ẩn thuộc tính password_hash khi chuyển đổi sang mảng, JSON
class User extends Authenticatable
{
    use HasFactory;

    public $timestamps           = false; // Không dùng cập nhật tự động created_at và updated_at
    protected $authPasswordName  = 'password_hash'; // Khai báo tên thuộc tính mật khẩu cho xác thực
    protected $rememberTokenName = '';  // Không sử dụng thuộc tính remember_token

    protected $fillable = [
        'username', 'full_name', 'password_hash', 'password_status', 'email', 'birth_date',
        'user_type', 'department', 'status', 'created_at', 'update_at',
    ];

    // Khai báo cách chuyển kiểu thuộc tính users
    protected function casts(): array
    {
        return [
            'status'          => 'integer',
            'password_status' => 'integer',
            'user_type'       => 'integer',
            'department'      => 'integer',
            'birth_date'      => 'date:Y-m-d',
            'created_at'      => 'datetime:Y-m-d H:i:s',
            'update_at'       => 'datetime:Y-m-d H:i:s',
        ];
    }

    // Lấy các đơn do người dùng tạo
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'user_id');
    }

    // Lấy các đơn chỉ định người dùng làm người duyệt
    public function assignedLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approver_id');
    }
}
