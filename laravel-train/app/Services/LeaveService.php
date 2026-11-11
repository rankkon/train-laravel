<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LeaveService
{
    public function sendMail(?string $email, Mailable $mail): void
    {
        if ($email) {
            try {
                Mail::to($email)->queue($mail);
            } catch (Throwable $error) {
                report($error);
            }
        }
    }
}
