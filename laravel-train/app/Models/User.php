<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    public const TYPE_USER = 1;

    public const TYPE_MANAGER = 2;

    public const TYPE_ADMIN = 3;

    public const STATUS_ACTIVE = 1;

    public const STATUS_LOCKED = 0;

    use HasFactory, Notifiable;

    public $timestamps = false;

    protected $authPasswordName = 'password_hash';

    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'user_type' => 'integer',
            'department' => 'integer',
            'birth_date' => 'date:Y-m-d',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'update_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /** Lấy các đơn do người dùng tạo. */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'user_id');
    }

    /** Lấy các đơn chỉ định người dùng làm người duyệt. */
    public function assignedLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approver_id');
    }
}
