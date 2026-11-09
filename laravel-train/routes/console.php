<?php

use App\Services\LeaveService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('leaves:clear-temp', function (LeaveService $leaveService): void {
    $count = $leaveService->deleteExpiredAttachments();
    $this->info(__('messages.temporary_attachments_deleted', ['count' => $count]));
})->purpose('Delete expired temporary leave attachments');

Schedule::command('leaves:clear-temp')->hourly()->withoutOverlapping();
