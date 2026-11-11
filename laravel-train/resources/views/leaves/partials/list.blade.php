<div class="leave-list-toolbar" data-page="{{ $leaves->currentPage() }}">
    {{-- Tìm kiếm gửi GET để giữ từ khóa trên URL và trong các liên kết phân trang. --}}
    <form method="get" action="{{ route('leaves.index') }}" id="leave-search-form" class="leave-search">
        <label for="leave-search">Tên user/Loại đơn/Nội dung</label>
        @if ($sortColumn !== '')
            <input type="hidden" name="sort" value="{{ $sortColumn }}">
            <input type="hidden" name="direction" value="{{ $sortDirection }}">
        @endif
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
                    @foreach ([
                        'stt'        => 'STT',
                        'full_name'  => 'Người dùng',
                        'leave_type' => 'Loại đơn',
                        'created_at' => 'Ngày lập',
                        'status'     => 'Trạng thái',
                        'end_date'   => 'Ngày duty',
                        'title'      => 'Mô tả',
                    ] as $column => $label)
                        @php
                            $isSorted       = $sortColumn === $column;
                            $nextDirection  = 'asc';
                            $sortIcon       = '↕';
                            $ariaSort       = 'none';
                            $sortParameters = ['q' => $search];
                            $sortAction     = 'trở về thứ tự mặc định';
                            if ($isSorted) {
                                $nextDirection = $sortDirection === 'asc' ? 'desc' : '';
                                $sortIcon      = $sortDirection === 'asc' ? '↑' : '↓';
                                $ariaSort      = $sortDirection === 'asc' ? 'ascending' : 'descending';
                            }
                            if ($nextDirection !== '') {
                                $sortParameters['sort']      = $column;
                                $sortParameters['direction'] = $nextDirection;
                                $sortAction                  = $nextDirection === 'asc' ? 'sắp xếp tăng dần' : 'sắp xếp giảm dần';
                            }
                        @endphp
                        <th scope="col" aria-sort="{{ $ariaSort }}">
                            <a href="{{ route('leaves.index', $sortParameters) }}"
                               class="leave-sort-link{{ $isSorted ? ' leave-sort-active' : '' }}"
                               aria-label="{{ $label }}: {{ $sortAction }}">
                                <span>{{ $label }}</span>
                                <span class="leave-sort-icon" aria-hidden="true">{{ $sortIcon }}</span>
                            </a>
                        </th>
                    @endforeach
                    <th scope="col">Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leaves as $leave)
                    {{-- Tính quyền theo từng đơn để chỉ hiển thị các nút xử lý phù hợp. --}}
                    @php
                        $canProcess = auth()->user()->can('process', $leave);
                        $canEdit    = auth()->user()->can('update', $leave);
                        $rowNumber  = $leaves->firstItem() + $loop->index;
                        if ($sortColumn === 'stt' && $sortDirection === 'desc') {
                            $rowNumber = $leaves->total() - $rowNumber + 1;
                        }
                    @endphp
                    <tr class="leave-row leave-status-{{ $leave->status }}">
                        <td>{{ $rowNumber }}</td> {{-- STT liên tục giữa các trang. --}}
                        <td>{{ $leave->full_name }}</td>
                        <td>{{ \App\Constants\AppConstants::LEAVE_TYPES[$leave->leave_type] }}</td>
                        <td>{{ $leave->created_at->toDateString() }}</td>
                        <td>{{ \App\Constants\AppConstants::LEAVE_STATUSES[$leave->status] }}</td>
                        <td>{{ $leave->end_date->toDateString() }}</td>
                        <td title="{{ $leave->title }}">
                            <span class="leave-title">{{ $leave->title }}</span>
                        </td>
                        <td>
                            @if ($canProcess || $canEdit)
                                <div class="leave-actions">

                                    @if ($canProcess)

                                        <button type="button" class="leave-approve" title="Duyệt" data-approve-url="{{ route('leaves.approve', $leave) }}">✓</button>
                                    @endif
                                    @if ($canEdit)
                                        <a href="{{ route('leaves.edit', ['leave' => $leave->id]) }}" class="leave-edit" title="Sửa">✎</a>
                                    @endif
                                    @if ($canProcess)
                                        <button type="button" class="leave-cancel" title="Hủy" data-cancel-url="{{ route('leaves.cancel', $leave) }}">×</button>
                                    @endif
                                </div>
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

{{ $leaves->links('components.pagination') }}
