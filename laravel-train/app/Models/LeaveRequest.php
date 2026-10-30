<?php

namespace App\Models;

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
