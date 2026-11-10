<?php

namespace App\Models;

use App\Constants\AppConstants;
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
    use HasFactory, Notifiable;

    public $timestamps           = false; // Không dùng cập nhật tự động created_at và updated_at
    protected $authPasswordName  = 'password_hash'; // Khai báo tên thuộc tính mật khẩu cho xác thực
    protected $rememberTokenName = '';  // Không sử dụng thuộc tính remember_token

    // Giới hạn người dùng được xem dữ liệu theo phòng ban
    #[Scope]
    protected function visibleTo(Builder $query, User $viewer): void
    {
        // Admin xem toàn bộ; tài khoản khác chỉ xem người dùng cùng phòng ban.
        if ($viewer->user_type !== AppConstants::TYPE_ADMIN) {
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

        $pattern          = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%'; // Thoát các ký tự đặc biệt trong chuỗi tìm kiếm để tìm đúng ký tự nhập vào
        $codeLength       = AppConstants::USER_CODE_LENGTH;
        $paddingThreshold = 10 ** ($codeLength - 1); // Xác định ngưỡng ID để đệm số 0 theo độ dài mã người dùng
        // Gom các điều kiện OR để tìm kiếm không vượt phạm vi phòng ban đã lọc.
        $query->where(function (Builder $matches) use ($pattern, $codeLength, $paddingThreshold): void {
            // Tạo mã hiển thị từ ID
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
        $query = self::query()->where('status', AppConstants::STATUS_ACTIVE); // Chỉ lấy người duyệt có tài khoản đang hoạt động.

        // User chọn Manager cùng phòng ban, Manager chọn Admin, Admin chọn chính mình để duyệt đơn
        if ($this->user_type === AppConstants::TYPE_USER) {
            $query->where('user_type', AppConstants::TYPE_MANAGER)
                ->where('department', $this->department);
        } elseif ($this->user_type === AppConstants::TYPE_MANAGER) {
            $query->where('user_type', AppConstants::TYPE_ADMIN);
        } else {
            $query->where('user_type', AppConstants::TYPE_ADMIN)->whereKey($this->id);
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
