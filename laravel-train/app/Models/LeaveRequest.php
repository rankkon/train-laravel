<?php

namespace App\Models;

use App\Constants\AppConstants;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $table                = 'leave'; // Khai báo tên bảng leave
    public $timestamps              = false; // Không dùng cập nhật tự động created_at và updated_at

    // Lọc đơn theo phạm vi được xem của người dùng
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->join('users', 'users.id', '=', 'leave.user_id'); // Lấy phòng ban theo người tạo và loại đơn không còn tài khoản người tạo.

        if ($user->user_type !== AppConstants::TYPE_ADMIN) {
            $query->where('users.department', $user->department);
        }
    }

    // Tìm đơn theo người tạo, tiêu đề hoặc loại đơn
    #[Scope]
    protected function matching(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $pattern    = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%'; // Thoát các ký tự đặc biệt để ký tự nhập vào được tìm kiếm như ký tự bình thường
        $comparison = "REGEXP_REPLACE(%s, ?, '') COLLATE utf8mb4_unicode_520_ci LIKE REGEXP_REPLACE(?, ?, '') ESCAPE '!'";
        // Gom các điều kiện để không làm thay đổi phạm vi đơn được xem
        $query->where(function (Builder $matches) use ($pattern, $comparison): void {
            $matches->whereRaw(sprintf($comparison, 'users.full_name'), ['\\p{M}', $pattern, '\\p{M}'])
                ->orWhereRaw(sprintf($comparison, '`leave`.title'), ['\\p{M}', $pattern, '\\p{M}']);
            // Tìm theo tên loại đơn hiển thị
            foreach (AppConstants::LEAVE_TYPES as $type => $name) {
                $matches->orWhereRaw('(`leave`.leave_type = ? AND '.sprintf($comparison, 'CAST(? AS CHAR)').')', [
                    $type, $name, '\\p{M}', $pattern, '\\p{M}',
                ]);
            }
        });
    }

    // Khai báo cách chuyển kiểu thuộc tính leave
    protected function casts(): array
    {
        return [
            'user_id'     => 'integer',
            'approver_id' => 'integer',
            'leave_type'  => 'integer',
            'status'      => 'integer',
            'start_date'  => 'date:Y-m-d',
            'end_date'    => 'date:Y-m-d',
            'approved_at' => 'date:Y-m-d',
            'created_at'  => 'datetime:Y-m-d H:i:s',
            'update_at'   => 'datetime:Y-m-d H:i:s',
            'delete_at'   => 'datetime:Y-m-d H:i:s',
        ];
    }

    // Lấy tài khoản tạo đơn
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Lấy tài khoản được chỉ định duyệt đơn
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
