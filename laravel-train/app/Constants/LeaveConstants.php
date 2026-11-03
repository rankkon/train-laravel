<?php

namespace App\Constants;

use App\Models\LeaveRequest;

class LeaveConstants
{
    public const LEAVES_PER_PAGE = 15;

    public const PAGINATION_NEARBY_PAGES = 2;

    public const CANCEL_REASON_MAX_BYTES = 65535;

    public const ATTACHMENT_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf';

    public const ATTACHMENT_MAX_MB = 10;

    public const ATTACHMENT_RANDOM_BYTES = 16;

    public const TITLE_MAX_LENGTH = 255;

    public const CONTENT_MAX_BYTES = 65535;

    public const ATTACHMENT_NAME_MAX_LENGTH = 255;

    public const ATTACHMENT_ALLOWED_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'pdf' => ['application/pdf'],
    ];

    public const LEAVE_TYPES = [
        LeaveRequest::TYPE_NGHIPHEP => 'Đơn nghỉ phép',
        LeaveRequest::TYPE_VATTU => 'Đơn cấp vật tư máy móc',
        LeaveRequest::TYPE_GIOLAM => 'Đơn thay đổi giờ làm',
        LeaveRequest::TYPE_CHIPHICONGTAC => 'Đơn xin thanh toán công tác phí',
    ];

    public const LEAVE_STATUSES = [
        LeaveRequest::STATUS_NEW => 'Đơn mới',
        LeaveRequest::STATUS_APPROVED => 'Đã duyệt',
        LeaveRequest::STATUS_CANCELLED => 'Đã hủy',
    ];
}
