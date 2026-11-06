<?php

namespace App\Mail;

use App\Constants\LeaveConstants;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Định nghĩa email thông báo kết quả xử lý đơn
class LeaveProcessedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3; // Thử tối đa 3 lần

    public array $backoff = [60, 60]; // Chờ 60s trước mỗi lần thử

    public int $timeout = 60; // Timeout xử lý gửi email

    // Nhập dữ liệu đơn, tên người tạo và kết quả xử lý đơn để dựng nội dung mail
    public function __construct(
        public int $leaveId,
        public array $values,
        public string $creatorName,
    ) {}

    // Chọn tiêu đề email theo trạng thái
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __($this->values['status'] === LeaveRequest::STATUS_APPROVED
                ? 'messages.leave_approved_mail_subject'
                : 'messages.leave_cancelled_mail_subject'),
        );
    }

    // Chuẩn bị nội dung hiện thị theo kết quả xử lý đơn
    public function content(): Content
    {
        $isApproved = $this->values['status'] === LeaveRequest::STATUS_APPROVED;

        return new Content(
            view: 'leave-processed-mail',
            with: [
                'leaveType'   => LeaveConstants::LEAVE_TYPES[(int) $this->values['leave_type']],
                'mailSubject' => $this->envelope()->subject,
                'mailIntro'   => __($isApproved ? 'messages.leave_approved_mail_intro' : 'messages.leave_cancelled_mail_intro'),
                'isApproved'  => $isApproved,
            ],
        );
    }
}
