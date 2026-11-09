<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $formTitle }} | Sanshin</title>
    <link rel="stylesheet" href="{{ asset('css/common.css') }}?v={{ filemtime(public_path('css/common.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/leave_form.css') }}?v={{ filemtime(public_path('css/leave_form.css')) }}">
</head>
<body class="leave-list-page leave-create-page">
    <header class="site-header">
        <span class="site-brand">Sanshin</span>
        <span class="site-description">Hệ thống quản lý đơn</span>
    </header>

    <main class="leave-list leave-create">
        <h1 class="leave-form-title">{{ $formTitle }}</h1>

        <form id="leave-form" class="leave-form" method="post" action="{{ $formAction }}" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="MAX_FILE_SIZE" value="{{ \App\Constants\AppConstants::ATTACHMENT_MAX_MB * 1024 * 1024 }}">
            @foreach ($originalValues as $field => $value)
                <input type="hidden" name="original_values[{{ $field }}]" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="exit_destination" value="{{ $exitDestination }}">
            <input type="hidden" name="attachment_token" value="{{ $attachment['token'] ?? '' }}">
            <div class="leave-form-fields">
                <p class="leave-form-error" data-form-error="form" @if (! isset($errors['form'])) hidden @endif>{{ $errors['form'] ?? '' }}</p>
                <div class="leave-form-field">
                    <label for="leave-title">Tiêu đề <span class="required-marker">*</span></label>
                    <input type="text" id="leave-title" name="title" value="{{ $values['title'] }}" maxlength="{{ \App\Constants\AppConstants::TITLE_MAX_LENGTH }}" required
                        class="{{ isset($errors['title']) ? 'leave-input-error' : '' }}">
                    <p class="leave-form-error" data-form-error="title" @if (! isset($errors['title'])) hidden @endif>{{ $errors['title'] ?? '' }}</p>
                </div>

                <div class="leave-form-field leave-form-content">
                    <label for="leave-content">Nội dung</label>
                    <textarea id="leave-content" name="content" class="{{ isset($errors['content']) ? 'leave-input-error' : '' }}">{{ $values['content'] }}</textarea>
                    <p class="leave-form-error" data-form-error="content" @if (! isset($errors['content'])) hidden @endif>{{ $errors['content'] ?? '' }}</p>
                </div>

                <div class="leave-form-field">
                    <label for="leave-approver">Người duyệt <span class="required-marker">*</span></label>
                    <div class="leave-select-control">
                        <select id="leave-approver" name="approver_id" required class="{{ isset($errors['approver_id']) ? 'leave-input-error' : '' }}">
                            @foreach ($approvers as $approver)
                                <option value="{{ $approver->id }}" @if ($values['approver_id'] === (string) $approver->id) selected @endif>{{ $approver->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="leave-form-error" data-form-error="approver_id" @if (! isset($errors['approver_id'])) hidden @endif>{{ $errors['approver_id'] ?? '' }}</p>
                </div>

                <div class="leave-form-field leave-form-type">
                    <label for="leave-type">Loại đơn <span class="required-marker">*</span></label>
                    <div class="leave-select-control">
                        <select id="leave-type" name="leave_type" required class="{{ isset($errors['leave_type']) ? 'leave-input-error' : '' }}">
                            @foreach ($leaveTypes as $typeCode => $typeName)
                                <option value="{{ $typeCode }}" @if ($values['leave_type'] === (string) $typeCode) selected @endif>{{ $typeName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="leave-form-error" data-form-error="leave_type" @if (! isset($errors['leave_type'])) hidden @endif>{{ $errors['leave_type'] ?? '' }}</p>
                </div>

                <div class="leave-form-field leave-form-date">
                    <label for="leave-start-date">Ngày bắt đầu <span class="required-marker">*</span></label>
                    <div class="leave-date-control">
                        <input type="date" id="leave-start-date" name="start_date" value="{{ $values['start_date'] }}" @if ($minimumDate !== '') min="{{ $minimumDate }}" @endif required
                            class="{{ isset($errors['start_date']) ? 'leave-input-error' : '' }}">
                    </div>
                    <p class="leave-form-error" data-form-error="start_date" @if (! isset($errors['start_date'])) hidden @endif>{{ $errors['start_date'] ?? '' }}</p>
                </div>

                <div class="leave-form-field leave-form-date">
                    <label for="leave-end-date">Ngày kết thúc <span class="required-marker">*</span></label>
                    <div class="leave-date-control">
                        <input type="date" id="leave-end-date" name="end_date" value="{{ $values['end_date'] }}" @if ($minimumDate !== '') min="{{ $minimumDate }}" @endif required
                            class="{{ isset($errors['end_date']) ? 'leave-input-error' : '' }}">
                    </div>
                    <p class="leave-form-error" data-form-error="end_date" @if (! isset($errors['end_date'])) hidden @endif>{{ $errors['end_date'] ?? '' }}</p>
                </div>

                <div class="leave-form-field">
                    <label for="leave-attachment">Đính kèm <span class="required-marker">*</span></label>
                    <div class="leave-file-control">
                        <input type="file" id="leave-attachment" name="attachment" accept="{{ \App\Constants\AppConstants::ATTACHMENT_ACCEPT }}" required
                            title="{{ __('messages.attachment_hint', ['size' => \App\Constants\AppConstants::ATTACHMENT_MAX_MB]) }}" class="{{ isset($errors['attachment']) ? 'leave-input-error' : '' }}">
                        <span id="leave-file-name" class="leave-file-name" title="{{ $attachment['name'] ?? '' }}">{{ $attachment['name'] ?? '' }}</span>
                        <span class="leave-upload-icon"></span>
                    </div>
                    <p class="leave-form-error" data-form-error="attachment" @if (! isset($errors['attachment'])) hidden @endif>{{ $errors['attachment'] ?? '' }}</p>
                </div>
            </div>

            <div class="leave-form-actions">
                <button type="submit" name="action" value="validate" class="leave-form-next">Lưu</button>
                <button type="submit" name="action" value="clear" class="leave-form-clear">Xóa trống</button>
            </div>
        </form>
    </main>
    @include('sidebar', ['sidebarFormId' => 'leave-form'])
    @include('form-popups', ['formId' => 'leave-form'])
</body>
</html>
