<?php

namespace App\Constants;

use App\Models\LeaveRequest;

class LeaveConstants
{
    public const LEAVES_PER_PAGE = 15; // Số lượng đơn trên mỗi trang

    public const PAGINATION_NEARBY_PAGES = 2; // Số lượng trang gần nhất hiển thị trong phân trang

    public const CANCEL_REASON_MAX_BYTES = 65535; // Số byte tối đa cho lý do hủy đơn

    public const ATTACHMENT_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf'; // Các loại tệp đính kèm được chấp nhận

    public const ATTACHMENT_MAX_MB = 10; // Kích thước tối đa của tệp đính kèm (MB)

    public const ATTACHMENT_RANDOM_BYTES = 16; // Số byte ngẫu nhiên để tạo tên tệp đính kèm

    public const TITLE_MAX_LENGTH = 255; // Độ dài tối đa của tiêu đề đơn

    public const CONTENT_MAX_BYTES = 65535; // Số byte tối đa của nội dung đơn

    public const ATTACHMENT_NAME_MAX_LENGTH = 255; // Độ dài tối đa của tên tệp đính kèm

    // Các loại tệp đính kèm được chấp nhận
    public const ATTACHMENT_ALLOWED_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'pdf' => ['application/pdf'],
    ];

    // Các loại đơn
    public const LEAVE_TYPES = [
        LeaveRequest::TYPE_NGHIPHEP => 'Đơn nghỉ phép',
        LeaveRequest::TYPE_VATTU => 'Đơn cấp vật tư máy móc',
        LeaveRequest::TYPE_GIOLAM => 'Đơn thay đổi giờ làm',
        LeaveRequest::TYPE_CHIPHICONGTAC => 'Đơn xin thanh toán công tác phí',
    ];

    // Các trạng thái đơn
    public const LEAVE_STATUSES = [
        LeaveRequest::STATUS_NEW => 'Đơn mới',
        LeaveRequest::STATUS_APPROVED => 'Đã duyệt',
        LeaveRequest::STATUS_CANCELLED => 'Đã hủy',
    ];
}
