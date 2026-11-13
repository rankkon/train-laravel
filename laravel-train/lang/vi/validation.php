<?php

return [
    'custom' => [
        'username'      => [
            'required' => '※Vui lòng nhập tên đăng nhập.',
            'max'      => '※Tên đăng nhập không được quá :max ký tự.',
            'unique'   => '※Tên đăng nhập đã tồn tại.',
        ],
        'full_name'     => [
            'required' => '※Vui lòng nhập tên người dùng.',
            'max'      => '※Tên người dùng không được quá :max ký tự.',
        ],
        'password'      => [
            'required' => '※Vui lòng nhập mật khẩu.',
        ],
        'email'         => [
            'required' => '※Vui lòng nhập email.',
            'email'    => '※Email không đúng định dạng.',
            'max'      => '※Email không được quá :max ký tự.',
            'unique'   => '※Email đã được sử dụng.',
        ],
        'birth_date'    => [
            'before_or_equal' => '※Ngày sinh không hợp lệ.',
        ],
        'user_type'     => [
            'in' => '※Vui lòng chọn loại người dùng trong danh sách.',
        ],
        'department'    => [
            'in' => '※Vui lòng chọn phòng ban trong danh sách.',
        ],
        'title'         => [
            'required' => '※Vui lòng nhập tiêu đề.',
            'max'      => '※Tiêu đề không được quá :max ký tự.',
        ],
        'approver_id'   => [
            'required' => '※Vui lòng chọn người duyệt đang hoạt động.',
            'in'       => '※Vui lòng chọn người duyệt đang hoạt động.',
        ],
        'leave_type'    => [
            'required' => '※Vui lòng chọn loại đơn.',
            'in'       => '※Vui lòng chọn loại đơn trong danh sách.',
        ],
        'start_date'    => [
            'required'       => '※Vui lòng chọn ngày bắt đầu.',
            'after_or_equal' => '※Ngày bắt đầu không thể trước hôm nay.',
        ],
        'end_date'      => [
            'required'       => '※Vui lòng chọn ngày kết thúc.',
            'after_or_equal' => '※Ngày kết thúc phải cùng hoặc sau ngày bắt đầu.',
        ],
        'attachment'    => [
            'required' => '※Vui lòng chọn một tệp ảnh hoặc PDF.',
            'mimes'    => '※Chỉ chấp nhận JPG/JPEG, PNG, GIF, WebP, BMP hoặc PDF.',
            'uploaded' => '※Tải tệp không thành công. Vui lòng chọn lại tệp.',
        ],
        'cancel_reason' => [
            'required' => '※Vui lòng nhập lý do hủy đơn.',
        ],
    ],
];
