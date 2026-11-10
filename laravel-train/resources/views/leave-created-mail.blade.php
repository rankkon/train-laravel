<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ __('messages.leave_created_mail_subject') }}</title>
</head>
<body>
    <p>Xin chào {{ $approverName }},</p>
    <p>{{ __('messages.leave_created_mail_intro', ['name' => $creatorName]) }}</p>

    {{-- Thông tin đơn mới gửi cho người duyệt; mã loại đơn đã được chuyển thành tên hiển thị. --}}
    <table>
        <tr><th align="left">Mã đơn:</th><td>{{ $leaveId }}</td></tr>
        <tr><th align="left">Người tạo:</th><td>{{ $creatorName }}</td></tr>
        <tr><th align="left">Tiêu đề:</th><td>{{ $values['title'] }}</td></tr>
        <tr><th align="left">Loại đơn:</th><td>{{ $leaveType }}</td></tr>
        <tr><th align="left">Ngày bắt đầu:</th><td>{{ $values['start_date'] }}</td></tr>
        <tr><th align="left">Ngày kết thúc:</th><td>{{ $values['end_date'] }}</td></tr>
    </table>

    {{-- Chỉ hiển thị nội dung khi người tạo có nhập và giữ nguyên xuống dòng. --}}
    @if ($values['content'] !== '')
        <p>Nội dung:</p>
        <p style="white-space: pre-wrap;">{{ $values['content'] }}</p>
    @endif

    <p><a href="{{ route('leaves.index') }}">Mở danh sách đơn</a></p>
</body>
</html>
