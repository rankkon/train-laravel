<div class="leave-list-toolbar" data-page="{{ $users->currentPage() }}">
    {{-- Tìm kiếm theo mã hoặc tên và lọc tài khoản hoạt động, đã khóa hoặc tất cả. --}}
    <form method="get" action="{{ route('users.index') }}" id="user-search-form" class="leave-search user-search">
        @if ($sortColumn !== '')
            <input type="hidden" name="sort" value="{{ $sortColumn }}">
            <input type="hidden" name="direction" value="{{ $sortDirection }}">
        @endif
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
        @can('create', \App\Models\User::class)
            <a href="{{ route('users.create') }}" class="leave-add">Thêm mới</a>
            <button type="button" id="user-bulk-lock" class="leave-add leave-cancel" disabled>Khóa nhiều</button>
        @endcan
    </div>
</div>
{{-- Khung bảng dùng chung cách cuộn ngang và dọc với danh sách đơn. --}}
<div class="leave-table-container">
    <div class="leave-table-scroll">
        <table class="leave-table user-table">
            <thead>
                <tr>
                    <th scope="col"><input type="checkbox" id="select-all-users"></th>
                    @foreach ([
                        'id'         => 'Mã người dùng',
                        'full_name'  => 'Tên người dùng',
                        'created_at' => 'Ngày lập',
                        'status'     => 'Trạng thái',
                    ] as $column => $label)
                        @php
                            $isSorted      = $sortColumn === $column;
                            $nextDirection = $isSorted ? ($sortDirection === 'asc' ? 'desc' : '') : 'asc';
                            $sortIcon      = $isSorted ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕';
                            $ariaSort      = $isSorted ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none';
                            $parameters    = ['q' => $search, 'status' => $statusFilter];
                            if ($nextDirection !== '') {
                                $parameters['sort']      = $column;
                                $parameters['direction'] = $nextDirection;
                            }
                        @endphp
                        <th scope="col" aria-sort="{{ $ariaSort }}">
                            <a href="{{ route('users.index', $parameters) }}" class="user-sort-link{{ $isSorted ? ' user-sort-active' : '' }}">
                                <span>{{ $label }}</span>
                                <span class="user-sort-icon" aria-hidden="true">{{ $sortIcon }}</span>
                            </a>
                        </th>
                    @endforeach
                    <th scope="col">Hành động</th>
                </tr>
            </thead>
            <tbody>
                {{-- Chỉ hiển thị thao tác trên những tài khoản mà người xem có quyền quản lý. --}}
                @forelse ($users as $user)
                    <tr>
                        <td><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" @cannot('manage', $user) disabled @endcannot></td>
                        <td>{{ str_pad((string) $user->id, \App\Constants\AppConstants::USER_CODE_LENGTH, '0', STR_PAD_LEFT) }}</td>
                        <td title="{{ $user->full_name }}">{{ $user->full_name }}</td>
                        <td>{{ $user->created_at->format('Y-m-d') }}</td>
                        <td>{{ \App\Constants\AppConstants::USER_STATUSES[$user->status] }}</td>
                        <td>
                            @can('manage', $user)
                                <div class="user-row-actions">
                                    <a href="{{ route('users.edit', $user) }}" class="user-edit" title="Sửa">✎</a>
                                    @if ($user->status === \App\Constants\AppConstants::STATUS_ACTIVE)
                                        <button type="button" class="user-cancel" title="Khóa" data-lock data-status-url="{{ route('users.lock', $user) }}" disabled>
                                            <svg class="user-status-icon" viewBox="0 0 24 24">
                                                <rect x="4" y="11" width="16" height="10" rx="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </button>
                                    @else
                                        <button type="button" class="user-activate" title="Mở khóa" data-status-url="{{ route('users.unlock', $user) }}">
                                            <svg class="user-status-icon" viewBox="0 0 24 24">
                                                <rect x="4" y="11" width="16" height="10" rx="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">{{ __('messages.no_users_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $users->links('components.pagination') }}
