<?php

namespace App\Models;

use App\Constants\UserConstants;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password_hash'])] // Ẩn thuộc tính password_hash khi chuyển đổi sang mảng, JSON
class User extends Authenticatable
{
    public const TYPE_USER = 1; // Mã vai trò: Người dùng

    public const TYPE_MANAGER = 2; // Mã vai trò: Quản lý

    public const TYPE_ADMIN = 3; // Mã vai trò: Quản trị viên

    public const STATUS_ACTIVE = 1; // Mã trạng thái: Hoạt động

    public const STATUS_LOCKED = 0; // Mã trạng thái: Bị khóa

    use HasFactory, Notifiable;

    public $timestamps = false; // Không dùng cập nhật tự động created_at và updated_at

    protected $authPasswordName = 'password_hash'; // Khai báo tên thuộc tính mật khẩu cho xác thực

    protected $rememberTokenName = '';  // Không sử dụng thuộc tính remember_token

    // Giới hạn người dùng được xem dữ liệu theo phòng ban
    #[Scope]
    protected function visibleTo(Builder $query, User $viewer): void
    {
        if ($viewer->user_type !== self::TYPE_ADMIN) {
            $query->where('department', $viewer->department);
        }
    }

    // Tìm kiếm người dùng theo tên hoặc mã người dùng
    #[Scope]
    protected function matching(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%'; // Thoát các ký tự đặc biệt trong chuỗi tìm kiếm để tìm đúng ký tự nhập vào
        $codeLength = UserConstants::USER_CODE_LENGTH;
        $paddingThreshold = 10 ** ($codeLength - 1); // Xác định ngưỡng ID để đệm số 0 theo độ dài mã người dùng
        $query->where(function (Builder $matches) use ($pattern, $codeLength, $paddingThreshold): void {
            $matches->whereRaw("CASE WHEN id < ? THEN LPAD(id, ?, '0') ELSE CAST(id AS CHAR) END LIKE ? ESCAPE '!'", [
                $paddingThreshold, $codeLength, $pattern,
            ])->orWhereRaw("full_name LIKE ? ESCAPE '!'", [$pattern]);
        });
    }

    // Khai báo cách chuyển kiểu thuộc tính users
    protected function casts(): array
    {
        return [
            'status'     => 'integer',
            'user_type'  => 'integer',
            'department' => 'integer',
            'birth_date' => 'date:Y-m-d',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'update_at'  => 'datetime:Y-m-d H:i:s',
        ];
    }

    // Lấy người duyệt phù hợp với tài khoản hiện tại
    public function leaveApprovers(): Collection
    {
        $query = self::query()->where('status', self::STATUS_ACTIVE);

        if ($this->user_type === self::TYPE_USER) {
            $query->where('user_type', self::TYPE_MANAGER)
                ->where('department', $this->department);
        } elseif ($this->user_type === self::TYPE_MANAGER) {
            $query->where('user_type', self::TYPE_ADMIN);
        } else {
            $query->where('user_type', self::TYPE_ADMIN)->whereKey($this->id);
        }

        return $query->orderBy('id')->get(['id', 'full_name']);
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
