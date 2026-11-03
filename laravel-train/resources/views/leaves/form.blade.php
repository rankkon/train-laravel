<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm mới đơn | Sanshin</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}?v={{ filemtime(public_path('css/common.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/leave_form.css') }}?v={{ filemtime(public_path('css/leave_form.css')) }}">
</head>
<body class="leave-list-page leave-create-page">
    <header class="site-header">
        <span class="site-brand">Sanshin</span>
        <span class="site-description">Hệ thống quản lý đơn</span>
    </header>

    <main class="leave-list leave-create">
        <h1 class="leave-form-title">Thêm mới đơn</h1>

        <form id="leave-form" class="leave-form{{ $isConfirmation ? ' leave-form-confirm' : '' }}" method="post" action="{{ route('leaves.create.submit') }}" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="MAX_FILE_SIZE" value="{{ \App\Constants\LeaveConstants::ATTACHMENT_MAX_MB * 1024 * 1024 }}">
            <div class="leave-form-fields">
                @if (isset($errors['form']))
                    <p class="leave-form-error">{{ $errors['form'] }}</p>
                @endif
                <div class="leave-form-field">
                    <label for="leave-title">Tiêu đề <span class="required-marker">*</span></label>
                    <input type="text" id="leave-title" name="title" value="{{ $values['title'] }}" maxlength="{{ \App\Constants\LeaveConstants::TITLE_MAX_LENGTH }}" required
                        @if ($isConfirmation) disabled @endif class="{{ isset($errors['title']) ? 'leave-input-error' : '' }}">
                    @if (isset($errors['title']))
                        <p class="leave-form-error">{{ $errors['title'] }}</p>
                    @endif
                </div>

                <div class="leave-form-field leave-form-content">
                    <label for="leave-content">Nội dung</label>
                    <textarea id="leave-content" name="content" @if ($isConfirmation) disabled @endif class="{{ isset($errors['content']) ? 'leave-input-error' : '' }}">{{ $values['content'] }}</textarea>
                    @if (isset($errors['content']))
                        <p class="leave-form-error">{{ $errors['content'] }}</p>
                    @endif
                </div>

                <div class="leave-form-field">
                    <label for="leave-approver">Người duyệt <span class="required-marker">*</span></label>
                    <div class="leave-select-control">
                        <select id="leave-approver" name="approver_id" required @if ($isConfirmation) disabled @endif class="{{ isset($errors['approver_id']) ? 'leave-input-error' : '' }}">
                            @foreach ($approvers as $approver)
                                <option value="{{ $approver->id }}" @if ($values['approver_id'] === (string) $approver->id) selected @endif>{{ $approver->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (isset($errors['approver_id']))
                        <p class="leave-form-error">{{ $errors['approver_id'] }}</p>
                    @endif
                </div>

                <div class="leave-form-field leave-form-type">
                    <label for="leave-type">Loại đơn <span class="required-marker">*</span></label>
                    <div class="leave-select-control">
                        <select id="leave-type" name="leave_type" required @if ($isConfirmation) disabled @endif class="{{ isset($errors['leave_type']) ? 'leave-input-error' : '' }}">
                            @foreach ($leaveTypes as $typeCode => $typeName)
                                <option value="{{ $typeCode }}" @if ($values['leave_type'] === (string) $typeCode) selected @endif>{{ $typeName }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (isset($errors['leave_type']))
                        <p class="leave-form-error">{{ $errors['leave_type'] }}</p>
                    @endif
                </div>

                <div class="leave-form-field leave-form-date">
                    <label for="leave-start-date">Ngày bắt đầu <span class="required-marker">*</span></label>
                    <div class="leave-date-control">
                        <input type="date" id="leave-start-date" name="start_date" value="{{ $values['start_date'] }}" min="{{ $minimumDate }}" required
                            @if ($isConfirmation) disabled @endif class="{{ isset($errors['start_date']) ? 'leave-input-error' : '' }}">
                        @if ($isConfirmation)
                            <span class="leave-calendar-icon"></span>
                        @endif
                    </div>
                    @if (isset($errors['start_date']))
                        <p class="leave-form-error">{{ $errors['start_date'] }}</p>
                    @endif
                </div>

                <div class="leave-form-field leave-form-date">
                    <label for="leave-end-date">Ngày kết thúc <span class="required-marker">*</span></label>
                    <div class="leave-date-control">
                        <input type="date" id="leave-end-date" name="end_date" value="{{ $values['end_date'] }}" min="{{ $minimumDate }}" required
                            @if ($isConfirmation) disabled @endif class="{{ isset($errors['end_date']) ? 'leave-input-error' : '' }}">
                        @if ($isConfirmation)
                            <span class="leave-calendar-icon"></span>
                        @endif
                    </div>
                    @if (isset($errors['end_date']))
                        <p class="leave-form-error">{{ $errors['end_date'] }}</p>
                    @endif
                </div>

                <div class="leave-form-field">
                    <label @if (! $isConfirmation) for="leave-attachment" @endif>Đính kèm <span class="required-marker">*</span></label>
                    @if ($isConfirmation)
                        <span id="leave-attachment" class="leave-confirm-attachment" title="{{ $attachment['name'] }}">{{ $attachment['name'] }}</span>
                    @else
                        <div class="leave-file-control">
                            <input type="file" id="leave-attachment" name="attachment" accept="{{ \App\Constants\LeaveConstants::ATTACHMENT_ACCEPT }}" required
                                title="{{ __('messages.attachment_hint', ['size' => \App\Constants\LeaveConstants::ATTACHMENT_MAX_MB]) }}" class="{{ isset($errors['attachment']) ? 'leave-input-error' : '' }}">
                            @if ($attachment)
                                <span class="leave-file-name" title="{{ mb_check_encoding($attachment['name'], 'UTF-8') ? $attachment['name'] : '' }}">{{ mb_check_encoding($attachment['name'], 'UTF-8') ? $attachment['name'] : '' }}</span>
                            @endif
                            <span class="leave-upload-icon"></span>
                        </div>
                        @if (isset($errors['attachment']))
                            <p class="leave-form-error">{{ $errors['attachment'] }}</p>
                        @endif
                    @endif
                </div>
            </div>

            <div class="leave-form-actions">
                @if ($isConfirmation)
                    <button name="action" value="confirm_save" class="leave-form-next">Lưu lại</button>
                    <button name="action" value="back" class="leave-form-clear">Quay lại</button>
                @else
                    <button name="action" value="validate" class="leave-form-next">Tiếp theo</button>
                    <button name="action" value="clear" class="leave-form-clear">Xóa trống</button>
                @endif
            </div>

            @if ($showSavePopup)
                <div class="popup-overlay">
                    <section class="popup-dialog">
                        <header class="popup-header">
                            <h2 class="popup-title">{{ __('messages.confirmation_title') }}</h2>
                            <button name="action" value="cancel_save" class="popup-close"></button>
                        </header>
                        <p class="popup-message">{{ __('messages.confirm_save') }}</p>
                        <div class="popup-actions">
                            <button name="action" value="save" class="popup-button popup-confirm">OK</button>
                            <button name="action" value="cancel_save" class="popup-button popup-cancel" autofocus>Cancel</button>
                        </div>
                    </section>
                </div>
            @endif
        </form>
    </main>

    @if ($showExitPopup)
        <div id="leave-exit-popup" class="popup-overlay">
            <section class="popup-dialog">
                <header class="popup-header">
                    <h2 class="popup-title">{{ __('messages.confirmation_title') }}</h2>
                    <button form="leave-form" name="action" value="cancel_exit" id="leave-exit-close" class="popup-close" title="Đóng"></button>
                </header>
                <p id="leave-exit-message" class="popup-message">{{ $exitMessage }}</p>
                <div class="popup-actions">
                    @if ($exitDestination === 'logout')
                        <button form="leave-form" formaction="{{ route('logout') }}" id="leave-exit-confirm" class="popup-button popup-confirm">Đồng ý</button>
                    @else
                        <button form="leave-form" name="action" value="confirm_exit" id="leave-exit-confirm" class="popup-button popup-confirm">Đồng ý</button>
                    @endif
                    <button form="leave-form" name="action" value="cancel_exit" id="leave-exit-cancel" class="popup-button popup-cancel" autofocus>Không</button>
                </div>
            </section>
        </div>
    @endif

    @include('sidebar', ['sidebarFormId' => 'leave-form'])

</body>
</html>
