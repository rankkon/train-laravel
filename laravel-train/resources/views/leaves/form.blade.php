@extends('layouts.app')

@section('title', $leave->exists ? 'Sửa đơn' : 'Thêm mới đơn')
@section('body-class', 'leave-list-page leave-create-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/leave_form.css') }}?v={{ filemtime(public_path('css/leave_form.css')) }}">
@endpush

@section('content')
    <main class="leave-list leave-create">
        <h1 class="leave-form-title">{{ $leave->exists ? 'Sửa đơn' : 'Thêm mới đơn' }}</h1>

        {{-- Form thêm/sửa đơn gửi cả dữ liệu và file bằng multipart/form-data, không lưu dữ liệu form vào session. --}}
        <form id="leave-form" class="leave-form" method="post" action="{{ $leave->exists ? route('leaves.update', $leave) : route('leaves.store') }}"
            enctype="multipart/form-data" data-record-form data-error-class="leave-input-error">
            @csrf
            @if ($leave->exists)
                @method('PUT')
            @endif
            <div class="leave-form-fields">
                <p class="leave-form-error" data-form-error="form" hidden></p>
                <div class="leave-form-field">
                    <label for="leave-title"><span class="required-marker">*</span> Tiêu đề</label>
                    <input type="text" id="leave-title" name="title" value="{{ $leave->title }}" maxlength="255" required>
                    <p class="leave-form-error" data-form-error="title" hidden></p>
                </div>

                <div class="leave-form-field leave-form-content">
                    <label for="leave-content">Nội dung</label>
                    <textarea id="leave-content" name="content">{{ $leave->content }}</textarea>
                    <p class="leave-form-error" data-form-error="content" hidden></p>
                </div>

                <div class="leave-form-field">
                    <label for="leave-approver"><span class="required-marker">*</span> Người duyệt</label>
                    <div class="leave-select-control">
                        <select id="leave-approver" name="approver_id" required>
                            @foreach ($approvers as $approver)
                                <option value="{{ $approver->id }}" @selected($leave->approver_id === $approver->id)>{{ $approver->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="leave-form-error" data-form-error="approver_id" hidden></p>
                    @if ($approvers->isEmpty())
                        <p class="leave-form-error">{{ __('messages.approver_unavailable') }}</p>
                    @endif
                </div>

                <div class="leave-form-field leave-form-type">
                    <label for="leave-type"><span class="required-marker">*</span> Loại đơn</label>
                    <div class="leave-select-control">
                        <select id="leave-type" name="leave_type" required>
                            @foreach (\App\Constants\AppConstants::LEAVE_TYPES as $code => $name)
                                <option value="{{ $code }}" @selected($leave->leave_type === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="leave-form-error" data-form-error="leave_type" hidden></p>
                </div>

                {{-- Ngày thêm mới bị giới hạn từ hôm nay; form sửa cho phép chọn ngày quá khứ. --}}
                <div class="leave-form-field leave-form-date">
                    <label for="leave-start-date"><span class="required-marker">*</span> Ngày bắt đầu</label>
                    <div class="leave-date-control">
                        <input type="date" id="leave-start-date" name="start_date" value="{{ $leave->start_date->toDateString() }}"
                            @if (!$leave->exists) min="{{ today()->toDateString() }}" @endif required>
                    </div>
                    <p class="leave-form-error" data-form-error="start_date" hidden></p>
                </div>

                <div class="leave-form-field leave-form-date">
                    <label for="leave-end-date"><span class="required-marker">*</span> Ngày kết thúc</label>
                    <div class="leave-date-control">
                        <input type="date" id="leave-end-date" name="end_date" value="{{ $leave->end_date->toDateString() }}" required>
                    </div>
                    <p class="leave-form-error" data-form-error="end_date" hidden></p>
                </div>

                <div class="leave-form-field">
                    <label for="leave-attachment">Đính kèm</label>
                    {{-- Ô file không được điền lại bằng HTML; tên file đang giữ được hiển thị từ dữ liệu server. --}}
                    <div class="leave-attachment-control">
                        <div class="leave-file-control">
                            <input type="file" id="leave-attachment" name="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp"
                                data-preview-url="{{ $leave->exists ? route('leaves.preview', $leave) : '' }}"
                                title="{{ __('messages.attachment_hint') }}">
                            <span id="leave-file-name" class="leave-file-name" data-original-name="{{ $leave->attachment_original_name }}"
                                title="{{ $leave->attachment_original_name }}">{{ $leave->attachment_original_name }}</span>
                            <span class="leave-upload-icon"></span>
                        </div>
                        <button type="button" id="leave-preview-button" class="leave-preview-button" aria-label="Xem ảnh đính kèm"
                            aria-haspopup="dialog" aria-controls="leave-preview-dialog" title="Xem ảnh đính kèm" hidden>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <p class="leave-form-error" data-form-error="attachment" hidden></p>
                </div>
            </div>

            <div class="leave-form-actions">
                <button type="submit" class="leave-form-next">Lưu</button>
                <button type="button" data-clear-form class="leave-form-clear">Xóa trống</button>
            </div>
        </form>
    </main>
    <dialog id="leave-preview-dialog" class="leave-preview-dialog" aria-label="Xem ảnh đính kèm">
        <img id="leave-file-preview" class="leave-file-preview" alt="Ảnh xem trước tệp đính kèm" draggable="false">
    </dialog>
@endsection

@push('scripts')
    <script src="{{ asset('js/leaves.js') }}" defer></script>
@endpush
