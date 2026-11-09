<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách người dùng | Sanshin</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}?v={{ filemtime(public_path('css/common.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/leave_list.css') }}?v={{ filemtime(public_path('css/leave_list.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/user_list.css') }}?v={{ filemtime(public_path('css/user_list.css')) }}">
</head>
<body class="leave-list-page leave-list-screen user-list-screen">
    <header class="site-header">
        <span class="site-brand">Sanshin</span>
        <span class="site-description">Hệ thống quản lý đơn</span>
    </header>

    @include('sidebar', ['sidebarFormId' => ''])

    <main class="leave-list">
        <div class="leave-list-toolbar">
            <form method="get" action="{{ route('users.index') }}" id="user-search-form" class="leave-search user-search">
                <label for="user-search">Mã/Tên user</label>
                <input type="text" id="user-search" name="q" value="{{ $search }}">
                <select id="user-status" name="status" class="user-status-filter" aria-label="Trạng thái người dùng">
                    <option value="1" @selected($statusFilter === '1')>Đang hoạt động</option>
                    <option value="0" @selected($statusFilter === '0')>Đã khóa</option>
                    <option value="" @selected($statusFilter === '')>Tất cả</option>
                </select>
            </form>
            <div class="leave-list-actions user-list-actions">
                <button type="submit" form="user-search-form" class="leave-search-button">Tìm kiếm</button>
                @if ($showUserListActions)
                    @can('create', \App\Models\User::class)
                        <a href="{{ route('users.create') }}" class="leave-add">Thêm mới</a>
                    @endcan
                    <form method="post" action="{{ $actionUrl }}" id="user-bulk-form">
                        <button name="action" value="open_bulk_lock" class="leave-add leave-cancel"
                            @if (! $users->contains(fn ($user) => $userActions[$user->id] && in_array($user->id, $selectedUserIds, true))) disabled @endif>Khóa nhiều</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="leave-table-container">
            <table class="leave-table user-table">
                <thead>
                    <tr>
                        <th scope="col"><input type="checkbox" id="select-all-users"></th>
                        <th scope="col">Mã người dùng</th>
                        <th scope="col">Tên người dùng</th>
                        <th scope="col">Ngày lập</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" form="user-bulk-form"
                                    @if (! $userActions[$user->id]) disabled @endif
                                    @if ($userActions[$user->id] && in_array($user->id, $selectedUserIds, true)) checked @endif>
                            </td>
                            <td>{{ str_pad((string) $user->id, \App\Constants\UserConstants::USER_CODE_LENGTH, '0', STR_PAD_LEFT) }}</td>
                            <td title="{{ $user->full_name }}">{{ $user->full_name }}</td>
                            <td>{{ substr((string) $user->getRawOriginal('created_at'), 0, 10) }}</td>
                            <td>{{ $userStatuses[$user->status] }}</td>
                            <td>
                                @if ($userActions[$user->id])
                                    <form method="post" action="{{ $actionUrl }}" class="user-row-actions">
                                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                                        <a href="{{ route('users.edit', ['user' => $user->id]) }}" class="user-edit" title="Sửa">✎</a>
                                        @if ($user->status === \App\Models\User::STATUS_ACTIVE)
                                            <button type="submit" name="action" value="open_lock" class="user-cancel" title="Khóa"
                                                @if (! in_array($user->id, $selectedUserIds, true)) disabled @endif>
                                                <svg class="user-status-icon" viewBox="0 0 24 24">
                                                    <rect x="4" y="11" width="16" height="10" rx="2"></rect>
                                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                                </svg>
                                            </button>
                                        @else
                                            <button type="submit" name="action" value="open_unlock" class="user-activate" title="Mở khóa">
                                                <svg class="user-status-icon" viewBox="0 0 24 24">
                                                    <rect x="4" y="11" width="16" height="10" rx="2"></rect>
                                                    <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
                                                </svg>
                                            </button>
                                        @endif
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ __('messages.no_users_found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links('pagination') }}
    </main>

    @if ($statusPopup !== '')
        <div class="popup-overlay">
            <form method="post" action="{{ $actionUrl }}" class="popup-dialog">
                @if ($statusPopup !== 'bulk_lock')
                    <input type="hidden" name="user_id" value="{{ $statusUserId }}">
                @endif
                <header class="popup-header">
                    <h2 class="popup-title">{{ __('messages.confirmation_title') }}</h2>
                    <button name="action" value="{{ $statusCloseAction }}" class="popup-close" title="Đóng"></button>
                </header>
                <p class="popup-message">{{ $statusMessage }}</p>
                <div class="popup-actions">
                    <button name="action" value="confirm_{{ $statusPopup }}" class="popup-button popup-confirm" autofocus>Đồng ý</button>
                    <button name="action" value="{{ $statusCloseAction }}" class="popup-button popup-cancel">Không</button>
                </div>
            </form>
        </div>
    @endif
    <script>
        const selectAllUsers = document.getElementById('select-all-users');
        const userCheckboxes = [...document.querySelectorAll('.user-table tbody input[name="user_ids[]"]:not(:disabled)')];
        const bulkLockButton = document.querySelector('#user-bulk-form button[value="open_bulk_lock"]');

        function updateUserSelection() {
            let selectedCount = 0;
            for (const checkbox of userCheckboxes) {
                if (checkbox.checked) {
                    selectedCount++;
                }
                const lockButton = checkbox.closest('tr').querySelector('button[value="open_lock"]');
                if (lockButton) {
                    lockButton.disabled = !checkbox.checked;
                }
            }
            if (bulkLockButton) {
                bulkLockButton.disabled = selectedCount === 0;
            }
            selectAllUsers.disabled      = userCheckboxes.length === 0;
            selectAllUsers.checked       = selectedCount > 0 && selectedCount === userCheckboxes.length;
            selectAllUsers.indeterminate = selectedCount > 0 && selectedCount < userCheckboxes.length;
        }

        selectAllUsers.addEventListener('change', function () {
            for (const checkbox of userCheckboxes) {
                checkbox.checked = selectAllUsers.checked;
            }
            updateUserSelection();
        });

        for (const checkbox of userCheckboxes) {
            checkbox.addEventListener('change', updateUserSelection);
        }
        window.addEventListener('pageshow', updateUserSelection);
        updateUserSelection();

        document.getElementById('user-status').addEventListener('change', function () {
            this.form.requestSubmit();
        });
    </script>
</body>
</html>
