<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LeaveService
{
    public function sendMail(?string $email, Mailable $mail): void
    {
        if ($email) {
            DB::afterCommit(function () use ($email, $mail): void {
                try {
                    Mail::to($email)->queue($mail);
                } catch (Throwable $error) {
                    report($error);
                }
            });
        }
    }
}
