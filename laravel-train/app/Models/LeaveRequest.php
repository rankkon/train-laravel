<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $table                = 'leave'; // Khai báo tên bảng leave
    public $timestamps              = false; // Không dùng cập nhật tự động created_at và updated_at

    protected $fillable = [
        'user_id', 'title', 'content', 'approver_id', 'leave_type', 'start_date', 'end_date',
        'status', 'created_at', 'approved_at', 'update_at', 'delete_at', 'cancel_reason',
        'attachment_original_name', 'attachment_path',
    ];

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
