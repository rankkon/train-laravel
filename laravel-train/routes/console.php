<?php

use App\Services\LeaveService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Lệnh mẫu của Laravel để hiển thị câu trích dẫn.
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dọn file đính kèm tạm đã hết hạn và hiển thị số thư mục đã xóa.
Artisan::command('leaves:clear-temp', function (LeaveService $leaveService): void {
    $count = $leaveService->deleteExpiredAttachments();
    $this->info(__('messages.temporary_attachments_deleted', ['count' => $count]));
})->purpose('Delete expired temporary leave attachments');

Schedule::command('leaves:clear-temp')->hourly()->withoutOverlapping(); // Dọn file mỗi giờ và tránh chạy chồng khi lượt trước chưa hoàn thành.
