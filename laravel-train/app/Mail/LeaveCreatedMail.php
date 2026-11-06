<?php

namespace App\Mail;

use App\Constants\LeaveConstants;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LeaveCreatedMail extends Mailable
{
    public function __construct(
        public int $leaveId,
        public array $values,
        public string $creatorName,
        public string $approverName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.leave_created_mail_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'leave-created-mail',
            with: ['leaveType' => LeaveConstants::LEAVE_TYPES[(int) $this->values['leave_type']]],
        );
    }
}
