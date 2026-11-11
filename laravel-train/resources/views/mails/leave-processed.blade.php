<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $mailSubject }}</title>
</head>
<body>
    <p>Xin chào {{ $creatorName }},</p>
    <p>{{ $mailIntro }}</p>

    {{-- Thông tin đơn và thời gian xử lý gửi cho người tạo sau khi duyệt hoặc hủy. --}}
    <table>
        <tr><th align="left">Mã đơn:</th><td>{{ $leaveId }}</td></tr>
        <tr><th align="left">Người tạo:</th><td>{{ $creatorName }}</td></tr>
        <tr><th align="left">Tiêu đề:</th><td>{{ $values['title'] }}</td></tr>
        <tr><th align="left">Loại đơn:</th><td>{{ $leaveType }}</td></tr>
        <tr><th align="left">Ngày bắt đầu:</th><td>{{ $values['start_date'] }}</td></tr>
        <tr><th align="left">Ngày kết thúc:</th><td>{{ $values['end_date'] }}</td></tr>
        <tr><th align="left">Thời gian xử lý:</th><td>{{ $values['processed_at'] }}</td></tr>
    </table>

    @if ($values['content'])
        <p>Nội dung:</p>
        <p style="white-space: pre-wrap;">{{ $values['content'] }}</p>
    @endif

    {{-- Chỉ hiển thị lý do hủy đối với kết quả không được duyệt. --}}
    @if (! $isApproved)
        <p>Lý do hủy:</p>
        <p style="white-space: pre-wrap;">{{ $values['cancel_reason'] }}</p>
    @endif

    <p><a href="{{ route('leaves.index') }}">Mở danh sách đơn</a></p>
</body>
</html>
