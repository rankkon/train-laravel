<?php

namespace App\Services;

use App\Constants\LeaveConstants;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class LeaveDraftService
{
    public function __construct(private LeaveValidationService $validation) {}

    // Tạo bộ dữ liệu trống cho form
    public function emptyValues(): array
    {
        return [
            'title'       => '', 
            'content'     => '', 
            'approver_id' => '',
            'leave_type'  => '', 
            'start_date'  => '', 
            'end_date'    => '',
        ];
    }

    // Chuẩn bị bản nháp và dữ liệu ban đầu
    public function prepare(Request $request, array $defaultValues): array
    {
        $session = $request->session();
        $userId = $request->user()->id;

        if ($request->isMethod('get') && ! $session->get('leave_create_resume')) {
            $this->clear($session);
        }
        $session->forget('leave_create_resume');

        $values = $defaultValues;
        $errors = [];
        $draft = $session->get('leave_create_draft');
        if ($draft && (int) $draft['user_id'] === $userId
            && isset($draft['leave_id']) && (int) $draft['leave_id'] === 0
            && $session->has('leave_create_initial')) {
            $values = $draft['values'];
            $errors = $draft['errors'] ?? [];
        } else {
            $this->clear($session);
        }

        $attachment = $session->get('leave_create_attachment');
        if ($attachment && (int) $attachment['user_id'] !== $userId) {
            $session->forget('leave_create_attachment');
            $attachment = null;
        }

        if (! $session->has('leave_create_initial')) {
            $session->put('leave_create_initial', [
                'values'     => $values,
                'attachment' => $this->attachmentFingerprint($attachment),
            ]);
            $session->put('leave_create_draft', [
                'user_id'   => $userId,
                'leave_id'  => 0, 
                'values'    => $values,
                'confirmed' => false, 
                'errors'    => [],
            ]);
        }

        return [
            'values'     => $values, 
            'errors'     => $errors, 
            'attachment' => $attachment,
            'initial'    => $session->get('leave_create_initial'),
        ];
    }

    // Đọc dữ liệu từ form, chỉ lấy các trường hợp lệ
    public function readValues(array $post): array
    {
        $values = $this->emptyValues();
        $errors = [];
        foreach (array_keys($values) as $field) {
            if (! isset($post[$field])) {
                continue;
            }
            if (! is_string($post[$field])) {
                $errors[$field] = __('messages.invalid_form_value');

                continue;
            }
            $values[$field] = $post[$field];
        }

        return ['values' => $values, 'errors' => $errors];
    }

    // Đọc file upload mới hoặc giữ file trong session
    public function readAttachment(Request $request, ?array $attachment, bool $keepTemporary = false): array
    {
        $result = ['attachment' => $attachment, 'error' => '']; // Không thay đổi file tạm nếu file mới lỗi
        $files = $request->files->all();
        $file = $files['attachment'] ?? null;
        if (count($files) > 1 || ($file !== null && ! $file instanceof UploadedFile)) { // Chỉ cho phép 1 file upload
            $result['error'] = __('messages.attachment_single_only');

            return $result;
        }
        if ($file === null) {
            return $this->checkStoredAttachment($result, $keepTemporary);
        }

        $maxSize = LeaveConstants::ATTACHMENT_MAX_MB * 1024 * 1024;
        $error = $file->getError();
        $size = is_file($file->getPathname()) ? $file->getSize() : 0;
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $size > $maxSize) {
            $result['error'] = __('messages.attachment_too_large', ['size' => LeaveConstants::ATTACHMENT_MAX_MB]);

            return $result;
        }
        if ($error !== UPLOAD_ERR_OK && $error !== UPLOAD_ERR_NO_FILE) {
            $result['error'] = __('messages.attachment_upload_failed');

            return $result;
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            return $this->checkStoredAttachment($result, $keepTemporary);
        }
        if (! $file->isValid()) {
            $result['error'] = __('messages.attachment_upload_invalid');

            return $result;
        }

        $data = @file_get_contents($file->getPathname(), false, null, 0, $maxSize + 1); // Giới hạn đọc file
        if ($data === false) {
            $result['error'] = __('messages.attachment_read_failed');

            return $result;
        }
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName())); // Chuẩn hóa tên file
        if (! $keepTemporary) {
            $result['error'] = $this->validation->attachment($name, $data);
            if ($result['error'] !== '') {
                return $result;
            }
        }
        $result['attachment'] = [
            'user_id'   => $request->user()->id,
            'name'      => $name,
            'data'      => $data,
            'temporary' => $keepTemporary,
        ];

        return $result;
    }

    // Kiểm tra file tạm đã giữ trong session
    private function checkStoredAttachment(array $result, bool $keepTemporary): array
    {
        if (! $keepTemporary && $result['attachment'] && ! empty($result['attachment']['temporary'])) {
            $attachment = $result['attachment'];
            $result['error'] = $this->validation->attachment($attachment['name'], $attachment['data']);
            if ($result['error'] === '') {
                $result['attachment']['temporary'] = false;
            }
        }
        if (! $keepTemporary && ! $result['attachment']) {
            $result['error'] = __('messages.attachment_required');
        }

        return $result;
    }

    // So sánh dữ liệu với trạng thái ban đầu
    public function hasChanges(array $values, ?array $attachment, array $initial): bool
    {
        foreach ($initial['values'] as $field => $initialValue) {
            $initialValue = str_replace(["\r\n", "\r"], "\n", $initialValue);
            $currentValue = str_replace(["\r\n", "\r"], "\n", $values[$field]);
            if ($currentValue !== $initialValue) {
                return true;
            }
        }

        return $this->attachmentFingerprint($attachment) !== $initial['attachment'];
    }

    // Tạo dấu nhận diện file bằng tên và hash dữ liệu
    public function attachmentFingerprint(?array $attachment): ?array
    {
        if (! $attachment) {
            return null;
        }

        return ['name' => $attachment['name'], 'hash' => hash('sha256', $attachment['data'])];
    }

    // Xóa dữ liệu nháp khỏi session
    public function clear(Session $session): void
    {
        $session->forget([
            'leave_create_draft', 'leave_create_attachment', 'leave_create_save_pending',
            'leave_create_initial', 'leave_create_exit',
        ]);
    }
}
