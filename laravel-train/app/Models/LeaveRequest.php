<?php

namespace App\Models;

use App\Constants\LeaveConstants;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    public const STATUS_NEW = 0;

    public const STATUS_APPROVED = 1;

    public const STATUS_CANCELLED = 2;

    public const TYPE_NGHIPHEP = 1;

    public const TYPE_VATTU = 2;

    public const TYPE_GIOLAM = 3;

    public const TYPE_CHIPHICONGTAC = 4;

    protected $table = 'leave';

    public $timestamps = false;

    /** Lọc đơn theo phạm vi phòng ban. */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->join('users', 'users.id', '=', 'leave.user_id');

        if ($user->user_type !== User::TYPE_ADMIN) {
            $query->where('users.department', $user->department);
        }
    }

    /** Tìm đơn theo người tạo, tiêu đề hoặc loại đơn. */
    #[Scope]
    protected function matching(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $comparison = "REGEXP_REPLACE(%s, ?, '') COLLATE utf8mb4_unicode_520_ci LIKE REGEXP_REPLACE(?, ?, '') ESCAPE '!'";
        $query->where(function (Builder $matches) use ($pattern, $comparison): void {
            $matches->whereRaw(sprintf($comparison, 'users.full_name'), ['\\p{M}', $pattern, '\\p{M}'])
                ->orWhereRaw(sprintf($comparison, '`leave`.title'), ['\\p{M}', $pattern, '\\p{M}'])
                ->orWhere('leave.leave_type', 0);
            foreach (LeaveConstants::LEAVE_TYPES as $type => $name) {
                $matches->orWhereRaw('(`leave`.leave_type = ? AND '.sprintf($comparison, 'CAST(? AS CHAR)').')', [
                    $type, $name, '\\p{M}', $pattern, '\\p{M}',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'approver_id' => 'integer',
            'leave_type' => 'integer',
            'status' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'approved_at' => 'date:Y-m-d',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'update_at' => 'datetime:Y-m-d H:i:s',
            'delete_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /** Lấy người tạo đơn. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Lấy người được chỉ định duyệt đơn. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
