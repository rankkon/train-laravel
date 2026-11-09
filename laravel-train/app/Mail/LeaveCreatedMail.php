<?php

namespace App\Mail;

use App\Constants\AppConstants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Định nghĩa email thông báo đơn mới
class LeaveCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries     = 3; // mail gửi tối đa 3 lần
    public array $backoff = [60, 60]; // Chờ 60s trước mỗi lần thử
    public int $timeout   = 60; // Timeout xử lý gửi mail

    // Nhập dữ liệu đơn, tên người tạo và người duyệt để dựng nội dung mail
    public function __construct(
        public int $leaveId,
        public array $values,
        public string $creatorName,
        public string $approverName,
    ) {}

    // Khai báo tiêu đề email
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.leave_created_mail_subject'),
        );
    }

    // Chuẩn bị nội dung hiển thị
    public function content(): Content
    {
        return new Content(
            view: 'leave-created-mail',
            with: ['leaveType' => AppConstants::LEAVE_TYPES[(int) $this->values['leave_type']]],
        );
    }
}
