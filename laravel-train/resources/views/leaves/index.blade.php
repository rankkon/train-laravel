<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách đơn | Sanshin</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}?v={{ filemtime(public_path('css/common.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/leave_list.css') }}?v={{ filemtime(public_path('css/leave_list.css')) }}">
</head>
<body class="leave-list-page leave-list-screen">
    <header class="site-header">
        <span class="site-brand">Sanshin</span>
        <span class="site-description">Hệ thống quản lý đơn</span>
    </header>

    @include('sidebar', ['sidebarFormId' => ''])

    <main class="leave-list">
        <div class="leave-list-toolbar">
            {{-- Tìm kiếm gửi GET để giữ từ khóa trên URL và trong các liên kết phân trang. --}}
            <form method="get" action="{{ route('leaves.index') }}" id="leave-search-form" class="leave-search">
                <label for="leave-search">Tên user/Loại đơn/Nội dung</label>
                <input type="text" id="leave-search" name="q" value="{{ $search }}">
            </form>
            <div class="leave-list-actions">
                <button type="submit" form="leave-search-form" class="leave-search-button">Tìm kiếm</button>
                <a href="{{ route('leaves.create') }}" class="leave-add">Thêm mới đơn</a>
            </div>
        </div>

        {{-- Khung ngoài cuộn ngang, khung trong cuộn dọc và ẩn thanh cuộn bên phải. --}}
        <div class="leave-table-container">
            <div class="leave-table-scroll">
                <table class="leave-table leave-management-table">
                    <thead>
                        <tr>
                            <th scope="col">STT</th>
                            <th scope="col">Người dùng</th>
                            <th scope="col">Loại đơn</th>
                            <th scope="col">Ngày lập</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Ngày duty</th>
                            <th scope="col">Mô tả</th>
                            <th scope="col">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaves as $leave)
                            {{-- Tính quyền theo từng đơn để chỉ hiển thị các nút xử lý phù hợp. --}}
                            @php
                                $canProcess = auth()->user()->can('process', $leave);
                                $canEdit    = auth()->user()->can('update', $leave);
                            @endphp
                            <tr class="leave-row leave-status-{{ $leave->status }}">
                                <td>{{ $leaves->firstItem() + $loop->index }}</td> {{-- STT liên tục giữa các trang. --}}
                                <td>{{ $leave->full_name }}</td>
                                <td>{{ $leaveTypes[$leave->leave_type] }}</td>
                                <td>{{ substr((string) $leave->getRawOriginal('created_at'), 0, 10) }}</td>
                                <td>{{ $leaveStatuses[$leave->status] }}</td>
                                <td>{{ $leave->getRawOriginal('end_date') }}</td>
                                <td title="{{ $leave->title }}">
                                    <span class="leave-title">{{ $leave->title }}</span>
                                </td>
                                <td>
                                    @if ($canProcess || $canEdit)
                                        <form method="post" action="{{ $actionUrl }}" class="leave-actions">
                                            @if ($canProcess)
                                                <input type="hidden" name="leave_id" value="{{ $leave->id }}">
                                                <button name="action" value="open_approve" class="leave-approve" title="Duyệt">✓</button>
                                            @endif
                                            @if ($canEdit)
                                                <a href="{{ route('leaves.edit', ['leave' => $leave->id]) }}" class="leave-edit" title="Sửa">✎</a>
                                            @endif
                                            @if ($canProcess)
                                                <button name="action" value="open_cancel" class="leave-cancel" title="Hủy">×</button>
                                            @endif
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8">{{ __('messages.no_leaves_found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $leaves->links('pagination') }}
    </main>

    {{-- Popup duyệt chỉ gửi xác nhận, quyền và trạng thái vẫn được kiểm tra lại khi thực hiện. --}}
    @if ($processPopup === 'approve')
        <div class="popup-overlay">
            <form method="post" action="{{ $actionUrl }}" class="popup-dialog">
                <input type="hidden" name="leave_id" value="{{ $processLeaveId }}">
                <header class="popup-header">
                    <h2 class="popup-title">{{ __('messages.confirmation_title') }}</h2>
                    <button name="action" value="close_popup" class="popup-close" title="Đóng" formnovalidate></button>
                </header>
                <p class="popup-message">{{ __('messages.confirm_approve') }}</p>
                <div class="popup-actions">
                    <button name="action" value="confirm_approve" class="popup-button popup-confirm" autofocus>OK</button>
                    <button name="action" value="close_popup" class="popup-button popup-cancel" formnovalidate>Cancel</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Popup hủy giữ lý do đã nhập và hiển thị lỗi do server trả về. --}}
    @if ($processPopup === 'cancel')
        <div class="popup-overlay">
            <form method="post" action="{{ $actionUrl }}" class="popup-dialog leave-cancel-dialog" novalidate>
                <input type="hidden" name="leave_id" value="{{ $processLeaveId }}">
                <header class="popup-header">
                    <h2 class="popup-title">{{ __('messages.confirmation_title') }}</h2>
                    <button name="action" value="close_popup" class="popup-close" title="Đóng"></button>
                </header>
                <div class="leave-cancel-fields">
                    <label for="cancel-reason">Lý do hủy đơn <span class="leave-cancel-required">*</span></label>
                    <textarea id="cancel-reason" name="cancel_reason" class="leave-cancel-reason{{ !empty($processErrors['cancel_reason']) ? ' leave-field-error' : '' }}" required>{{ mb_check_encoding($cancelReason, 'UTF-8') ? $cancelReason : '' }}</textarea>
                    @if (!empty($processErrors['cancel_reason']))
                        <p class="leave-process-error">{{ $processErrors['cancel_reason'] }}</p>
                    @endif
                </div>
                <div class="popup-actions leave-cancel-actions">
                    <button name="action" value="confirm_cancel" class="popup-button popup-confirm" autofocus>OK</button>
                </div>
            </form>
        </div>
    @endif
</body>
</html>
