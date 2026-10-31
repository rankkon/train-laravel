<?php

use App\Models\LeaveRequest;

return [
    'leaves_per_page' => 15,
    'pagination_nearby_pages' => 2,
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
