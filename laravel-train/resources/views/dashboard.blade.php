<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Sanshin</title>
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
        <div class="leave-list-toolbar"></div>

        <div class="leave-table-container">
            <table class="leave-table">
                <thead>
                    <tr>
                        <th scope="col">STT</th>
                        <th scope="col">Người dùng</th>
                        <th scope="col">Loại đơn</th>
                        <th scope="col">Ngày lập</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col">Ngày duty</th>
                        <th scope="col">Mô tả</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leaves as $leave)
                        <tr class="leave-row leave-status-{{ $leave->status }}">
                            <td>{{ $leaves->firstItem() + $loop->index }}</td>
                            <td>{{ $leave->full_name }}</td>
                            <td>{{ $leaveTypes[$leave->leave_type] }}</td>
                            <td>{{ substr((string) $leave->getRawOriginal('created_at'), 0, 10) }}</td>
                            <td>{{ $leaveStatuses[$leave->status] }}</td>
                            <td>{{ $leave->getRawOriginal('end_date') }}</td>
                            <td>
                                <div class="leave-description">
                                    <span class="leave-title" title="{{ $leave->title }}">{{ $leave->title }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('messages.no_leaves_found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $leaves->links('pagination') }}
    </main>
</body>
</html>
