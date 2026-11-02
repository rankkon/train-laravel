<?php

use App\Models\LeaveRequest;

return [
    'leaves_per_page' => 15,
    'pagination_nearby_pages' => 2,
    'cancel_reason_max_bytes' => 65535,
    'attachment_accept' => '.jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf',
    'attachment_max_mb' => 10,
    'title_max_length' => 255,
    'content_max_bytes' => 65535,
    'attachment_name_max_length' => 255,
    'attachment_allowed_types' => [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'pdf' => ['application/pdf'],
    ],
    'leave_types' => [
        LeaveRequest::TYPE_NGHIPHEP => 'Đơn nghỉ phép',
        LeaveRequest::TYPE_VATTU => 'Đơn cấp vật tư máy móc',
        LeaveRequest::TYPE_GIOLAM => 'Đơn thay đổi giờ làm',
        LeaveRequest::TYPE_CHIPHICONGTAC => 'Đơn xin thanh toán công tác phí',
    ],
    'leave_statuses' => [
        LeaveRequest::STATUS_NEW => 'Đơn mới',
        LeaveRequest::STATUS_APPROVED => 'Đã duyệt',
        LeaveRequest::STATUS_CANCELLED => 'Đã hủy',
    ],
];
