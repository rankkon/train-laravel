<?php

namespace App\Services;

use App\Constants\AppConstants;
use App\Mail\LeaveCreatedMail;
use App\Mail\LeaveProcessedMail;
use App\Models\LeaveRequest;
use App\Models\User;
use finfo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class LeaveService
{
    private string $uploadDirectory;

    public function __construct(?string $uploadDirectory = null)
    {
        $this->uploadDirectory = $uploadDirectory ?? base_path('../../train-php/uploads');
    }

    // Lưu đơn mới và file đính kèm trong transaction
    public function save(int $userId, array $values, ?array $attachment, int $leaveId = 0): array
    {
        $fullPath       = null;
        $leave          = null;
        $attachmentPath = '';
        $createdMail    = null;
        $approverEmail  = '';

        try {
            DB::transaction(function () use ($userId, $values, $attachment, $leaveId, &$fullPath, &$leave, &$attachmentPath, &$createdMail, &$approverEmail): void {
                $ownerId = $userId;
                if ($leaveId > 0) {
                    $leave = LeaveRequest::query()->whereKey($leaveId)->lockForUpdate()->first();
                    abort_if($leave === null, 404);
                    $ownerId = $leave->user_id;
                }
                $users = User::query()
                    ->whereIn('id', [$userId, $ownerId, (int) $values['approver_id']])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $currentUser = $users->get($userId);
                if ($currentUser === null || $currentUser->status !== AppConstants::STATUS_ACTIVE) {
                    throw new AuthenticationException;
                }
                if ($leave !== null) {
                    Gate::forUser($currentUser)->authorize('update', $leave);
                }

                $errors = $this->validateFields(
                    $values, $currentUser->leaveApprovers(), AppConstants::LEAVE_TYPES,
                    $leaveId === 0 ? now()->toDateString() : '',
                );
                if (! $attachment || (int) $attachment['user_id'] !== $userId) {
                    $fileError = __('messages.attachment_select_again');
                } else {
                    $fileError = $this->validateAttachment($attachment['name'], $attachment['data']);
                }
                if ($fileError === '' && isset($attachment['token'])) {
                    $temporary = $this->readTemporaryAttachment($userId, $leaveId, $attachment['token']);
                    if ($temporary === null || $temporary['name'] !== $attachment['name'] || $temporary['data'] !== $attachment['data']) {
                        $fileError = __('messages.attachment_select_again');
                    }
                }
                if ($fileError !== '') {
                    $errors['attachment'] = $fileError;
                }
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors); // Dừng transaction và trả về lỗi nếu có lỗi dữ liệu
                }

                $reuseAttachment = false;
                if ($leave !== null && isset($attachment['path']) && $attachment['path'] === $leave->attachment_path) {
                    $savedAttachment = $this->readSavedAttachment($leave, $userId);
                    $reuseAttachment = $savedAttachment !== null
                        && $savedAttachment['name'] === $attachment['name']
                        && $savedAttachment['data'] === $attachment['data'];
                }
                if ($reuseAttachment) {
                    $attachmentPath = $leave->attachment_path;
                } else {
                    if (! is_dir($this->uploadDirectory)) { // Yêu cầu thư mục upload tồn tại
                        throw new RuntimeException(__('messages.upload_directory_missing'));
                    }
                    $extension      = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));
                    $filename       = bin2hex(random_bytes(AppConstants::ATTACHMENT_RANDOM_BYTES)).'.'.$extension;
                    $attachmentPath = 'uploads/'.$filename;
                    $fullPath       = $this->uploadDirectory.DIRECTORY_SEPARATOR.$filename;
                    $writtenBytes   = @file_put_contents($fullPath, $attachment['data'], LOCK_EX);
                    if ($writtenBytes !== strlen($attachment['data'])) {
                        throw new RuntimeException(__('messages.attachment_write_failed'));
                    }
                }

                $changes = [
                    'title'                    => $values['title'],
                    'content'                  => $values['content'],
                    'approver_id'              => (int) $values['approver_id'],
                    'leave_type'               => (int) $values['leave_type'],
                    'start_date'               => $values['start_date'],
                    'end_date'                 => $values['end_date'],
                    'attachment_original_name' => $attachment['name'],
                    'attachment_path'          => $attachmentPath,
                ];
                if ($leaveId > 0) {
                    LeaveRequest::query()->whereKey($leaveId)->update($changes);
                } else {
                    $createdLeaveId = LeaveRequest::query()->insertGetId($changes + [
                        'user_id'    => $userId,
                        'status'     => AppConstants::STATUS_NEW,
                        'created_at' => now()->format('Y-m-d H:i:s'),
                    ]);
                    $approver      = $users->get((int) $values['approver_id']);
                    $approverEmail = trim((string) $approver?->email);
                    if ($approverEmail !== '') { // Chỉ chuẩn bị mail nếu người duyệt có email
                        $createdMail = new LeaveCreatedMail(
                            $createdLeaveId, $values, $currentUser->full_name, $approver->full_name,
                        );
                    }
                }
            });
        } catch (ValidationException $error) {
            return array_map(fn (array $messages): string => $messages[0], $error->errors());
        } catch (AuthenticationException $error) {
            throw $error;
        } catch (Throwable $error) {
            $this->rollbackTransaction();
            if ($fullPath !== null && is_file($fullPath) && ! @unlink($fullPath)) { // Dọn dẹp file đính kèm nếu có lỗi xảy ra
                Log::error(__('messages.failed_attachment_delete'));
            }

            throw $error;
        }

        if ($leave !== null && $leave->attachment_path !== $attachmentPath) {
            $this->removeOldAttachment($leave->attachment_path);
        }

        if (isset($attachment['token'])) {
            DB::afterCommit(fn () => $this->deleteTemporaryAttachment($userId, $leaveId, $attachment['token']));
        }

        // Đưa thông báo người duyệt vào queue
        if ($createdMail !== null) {
            $this->queueMailAfterCommit($approverEmail, $createdMail);
        }

        return [];
    }

    // Đọc file đính kèm đã lưu từ cơ sở dữ liệu
    public function readSavedAttachment(LeaveRequest $leave, int $userId): ?array
    {
        $fullPath = $this->attachmentFullPath($leave->attachment_path);
        if ($fullPath === null) {
            return null;
        }
        $data = @file_get_contents($fullPath, false, null, 0, AppConstants::ATTACHMENT_MAX_MB * 1024 * 1024 + 1);
        if ($data === false) {
            return null;
        }

        return [
            'user_id' => $userId,
            'name'    => $leave->attachment_original_name,
            'data'    => $data,
            'path'    => $leave->attachment_path,
        ];
    }

    // Kiểm tra đường dẫn thực của file đính kèm
    private function attachmentFullPath(string $path): ?string
    {
        $uploadDirectory  = realpath($this->uploadDirectory);
        $projectDirectory = dirname(rtrim($this->uploadDirectory, '/\\'));
        $fullPath         = realpath($projectDirectory.DIRECTORY_SEPARATOR.$path);
        if ($uploadDirectory === false || $fullPath === false || ! is_file($fullPath)) {
            return null;
        }
        if (! str_starts_with($fullPath, $uploadDirectory.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $fullPath;
    }

    // Xóa file cũ sau commit nếu không còn đơn nào sử dụng
    private function removeOldAttachment(string $path): void
    {
        try {
            if (LeaveRequest::query()->where('attachment_path', $path)->exists()) {
                return;
            }
            $fullPath = $this->attachmentFullPath($path);
            if ($fullPath !== null && ! @unlink($fullPath)) {
                Log::error(__('messages.old_attachment_delete_failed'));
            }
        } catch (Throwable) {
            Log::error(__('messages.old_attachment_delete_failed'));
        }
    }

    // Duyệt hoặc hủy đơn trong transaction và đưa email thông báo vào queue
    public function process(int $userId, int $leaveId, string $action, string $reason): void
    {
        $processedMail = null;
        $creatorEmail  = '';

        try {
            DB::transaction(function () use ($userId, $leaveId, $action, $reason, &$processedMail, &$creatorEmail): void {
                $leave = LeaveRequest::query()
                    ->whereKey($leaveId)
                    ->lockForUpdate()
                    ->first();
                abort_if($leave === null, 404);

                $users = User::query()
                    ->whereIn('id', [$userId, $leave->user_id, $leave->approver_id])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $currentUser = $users->get($userId);
                if ($currentUser === null || $currentUser->status !== AppConstants::STATUS_ACTIVE) {
                    throw new AuthenticationException;
                }

                $owner = $users->get($leave->user_id);
                abort_if($owner === null, 404);
                $leave->setRelation('user', $owner);
                abort_unless(Gate::forUser($currentUser)->allows('process', $leave), 409);

                $processedAt = now();
                if ($action === 'approve') {
                    $changes = [
                        'status'      => AppConstants::STATUS_APPROVED,
                        'update_at'   => $processedAt->format('Y-m-d H:i:s'),
                        'approved_at' => $processedAt->toDateString(),
                    ];
                } else {
                    $changes = [
                        'status'        => AppConstants::STATUS_CANCELLED,
                        'delete_at'     => $processedAt->format('Y-m-d H:i:s'),
                        'cancel_reason' => $reason,
                    ];
                }

                $updated = LeaveRequest::query()
                    ->whereKey($leaveId)
                    ->where('status', AppConstants::STATUS_NEW)
                    ->update($changes);

                $creatorEmail = trim((string) $owner->email);
                if ($updated > 0 && $creatorEmail !== '') { // Chỉ chuẩn bị email khi đơn đã được cập nhật và người tạo có email
                    $processedMail = new LeaveProcessedMail(
                        $leaveId,
                        [
                            'title'         => $leave->title,
                            'content'       => (string) $leave->content,
                            'leave_type'    => $leave->leave_type,
                            'start_date'    => $leave->getRawOriginal('start_date'),
                            'end_date'      => $leave->getRawOriginal('end_date'),
                            'status'        => $changes['status'],
                            'processed_at'  => $processedAt->format('Y-m-d H:i:s'),
                            'cancel_reason' => $action === 'approve' ? '' : $reason,
                        ],
                        $owner->full_name,
                    );
                }
            });
        } catch (Throwable $error) {
            $this->rollbackTransaction();

            throw $error;
        }

        // Sau khi commit thành công, đưa thông báo email vào queue
        if ($processedMail !== null) {
            $this->queueMailAfterCommit($creatorEmail, $processedMail);
        }
    }

    // Đưa mail vào queue sau khi transaction thành công
    private function queueMailAfterCommit(string $recipientEmail, Mailable $mail): void
    {
        DB::afterCommit(function () use ($recipientEmail, $mail): void {
            try {
                Mail::to($recipientEmail)->queue($mail);
            } catch (Throwable $error) {
                report($error);
            }
        });
    }

    // Rollback transaction nếu có lỗi xảy ra
    private function rollbackTransaction(): void
    {
        try {
            $connection = DB::connection();
            $pdo        = $connection->getPdo();
            if ($pdo->inTransaction()) {
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                } else {
                    $pdo->rollBack(); // Rollback trực tiếp qua PDO nếu transaction trả về 0
                }
            }
        } catch (Throwable) {
            Log::error(__('messages.leave_rollback_failed'));
        }
    }

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

    public function readAttachment(Request $request, ?LeaveRequest $leave = null): array
    {
        $userId   = $request->user()->id;
        $leaveId  = $leave?->id ?? 0;
        $rawToken = $request->post('attachment_token', '');
        $previous = null;
        if ($rawToken !== '') {
            $previous = $this->readTemporaryAttachment($userId, $leaveId, $rawToken);
        } elseif ($leave !== null) {
            $previous = $this->readSavedAttachment($leave, $userId);
        }
        $result = ['attachment' => $previous, 'error' => ''];
        $files  = $request->files->all();
        $file   = $files['attachment'] ?? null;
        if (count($files) > 1 || ($file !== null && ! $file instanceof UploadedFile)) { // Chỉ cho phép 1 file upload
            $result['error'] = __('messages.attachment_single_only');

            return $result;
        }
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            if ($result['attachment'] === null) {
                $result['error'] = $rawToken === '' ? __('messages.attachment_required') : __('messages.attachment_select_again');
            } else {
                $attachment      = $result['attachment'];
                $result['error'] = $this->validateAttachment($attachment['name'], $attachment['data']);
            }

            return $result;
        }
        $maxSize = AppConstants::ATTACHMENT_MAX_MB * 1024 * 1024;
        $error   = $file->getError();
        $size    = is_file($file->getPathname()) ? $file->getSize() : 0;
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $size > $maxSize) {
            $result['error'] = __('messages.attachment_too_large', ['size' => AppConstants::ATTACHMENT_MAX_MB]);

            return $result;
        }
        if ($error !== UPLOAD_ERR_OK || ! $file->isValid()) {
            $result['error'] = __('messages.attachment_upload_failed');

            return $result;
        }
        $data = @file_get_contents($file->getPathname(), false, null, 0, $maxSize + 1); // Giới hạn đọc file
        if ($data === false) {
            $result['error'] = __('messages.attachment_read_failed');

            return $result;
        }
        $name            = basename(str_replace('\\', '/', $file->getClientOriginalName())); // Chuẩn hóa tên file
        $result['error'] = $this->validateAttachment($name, $data);
        if ($result['error'] === '') {
            $token     = bin2hex(random_bytes(AppConstants::ATTACHMENT_RANDOM_BYTES));
            $directory = AppConstants::ATTACHMENT_TEMP_DIRECTORY.'/'.$userId.'/'.$leaveId.'/'.$token;
            $path      = $directory.'/attachment';
            $disk      = Storage::disk('local');
            if (! $disk->put($path, $data) || ! $disk->put($directory.'/name', $name) || ! @touch($disk->path($path), now()->timestamp)) {
                $disk->deleteDirectory($directory);
                throw new RuntimeException(__('messages.attachment_write_failed'));
            }
            $result['attachment'] = ['user_id' => $userId, 'name' => $name, 'data' => $data, 'token' => $token];
            if ($rawToken !== '') {
                $this->deleteTemporaryAttachment($userId, $leaveId, $rawToken);
            }
        }

        return $result;
    }

    public function readTemporaryAttachment(int $userId, int $leaveId, mixed $token): ?array
    {
        if (! $this->isTemporaryToken($token)) {
            return null;
        }

        $directory = AppConstants::ATTACHMENT_TEMP_DIRECTORY.'/'.$userId.'/'.$leaveId.'/'.$token;
        $path      = $directory.'/attachment';
        $disk      = Storage::disk('local');
        if (! $disk->exists($path) || ! $disk->exists($directory.'/name')) {
            return null;
        }
        clearstatcache(true, $disk->path($path));
        if ($disk->lastModified($path) <= now()->timestamp - AppConstants::ATTACHMENT_TEMP_TTL) {
            $this->deleteTemporaryAttachment($userId, $leaveId, $token);

            return null;
        }
        if ($disk->size($path) > AppConstants::ATTACHMENT_MAX_MB * 1024 * 1024) {
            return null;
        }

        return ['user_id' => $userId, 'name' => $disk->get($directory.'/name'), 'data' => $disk->get($path), 'token' => $token];
    }

    public function deleteTemporaryAttachment(int $userId, int $leaveId, mixed $token): bool
    {
        if (! $this->isTemporaryToken($token)) {
            return false;
        }

        $directory = AppConstants::ATTACHMENT_TEMP_DIRECTORY.'/'.$userId.'/'.$leaveId.'/'.$token;
        try {
            if (Storage::disk('local')->deleteDirectory($directory)) {
                return true;
            }
            Log::error(__('messages.failed_attachment_delete'));
        } catch (Throwable) {
            Log::error(__('messages.failed_attachment_delete'));
        }

        return false;
    }

    public function deleteExpiredAttachments(): int
    {
        $disk    = Storage::disk('local');
        $deleted = 0;
        $prefix  = AppConstants::ATTACHMENT_TEMP_DIRECTORY;
        foreach ($disk->allFiles($prefix) as $path) {
            if (! str_starts_with($path, $prefix.'/')) {
                continue;
            }
            $parts = explode('/', substr($path, strlen($prefix) + 1));
            if (count($parts) !== 4 || $parts[3] !== 'attachment' || ! $this->isTemporaryToken($parts[2])) {
                continue;
            }
            $userId  = filter_var($parts[0], FILTER_VALIDATE_INT);
            $leaveId = filter_var($parts[1], FILTER_VALIDATE_INT);
            if ($userId === false || $userId < 1 || (string) $userId !== $parts[0]) {
                continue;
            }
            if ($leaveId === false || $leaveId < 0 || (string) $leaveId !== $parts[1]) {
                continue;
            }
            clearstatcache(true, $disk->path($path));
            if ($disk->lastModified($path) <= now()->timestamp - AppConstants::ATTACHMENT_TEMP_TTL) {
                if ($this->deleteTemporaryAttachment($userId, $leaveId, $parts[2])) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function isTemporaryToken(mixed $token): bool
    {
        return is_string($token)
            && strlen($token) === 32
            && strspn($token, '0123456789abcdef') === 32;
    }

    // Kiểm tra các trường dữ liệu và trả về mảng lỗi
    public function validateFields(array $values, Collection $approvers, array $leaveTypes, string $minimumDate): array
    {
        $messages = [
            'title'       => $this->title($values['title']),
            'content'     => $this->content($values['content']),
            'approver_id' => $this->approver($values['approver_id'], $approvers),
            'leave_type'  => $this->type($values['leave_type'], $leaveTypes),
            'start_date'  => $this->date($values['start_date'], 'Ngày bắt đầu', $minimumDate),
            'end_date'    => $this->date($values['end_date'], 'Ngày kết thúc', $minimumDate),
        ];
        $errors = [];
        foreach ($messages as $field => $message) {
            if ($message !== '') {
                $errors[$field] = $message;
            }
        }
        if (! isset($errors['start_date']) && ! isset($errors['end_date'])
            && $values['start_date'] > $values['end_date']) {
            $errors['end_date'] = __('messages.end_date_before_start');
        }

        return $errors;
    }

    // Kiểm tra tiêu đề hợp lệ và giới hạn ký tự
    private function title(string $title): string
    {
        if (! mb_check_encoding($title, 'UTF-8')) {
            return __('messages.leave_title_encoding');
        }
        if (mb_trim($title) === '') {
            return __('messages.leave_title_required');
        }
        if (mb_strlen($title, 'UTF-8') > AppConstants::TITLE_MAX_LENGTH) {
            return __('messages.leave_title_too_long', ['max' => AppConstants::TITLE_MAX_LENGTH]);
        }

        return '';
    }

    // Kiểm tra định dạng và dung lượng nội dung
    private function content(string $content): string
    {
        if (! mb_check_encoding($content, 'UTF-8')) {
            return __('messages.leave_content_encoding');
        }
        if (strlen($content) > AppConstants::CONTENT_MAX_BYTES) {
            return __('messages.leave_content_too_long');
        }

        return '';
    }

    // Kiểm tra người duyệt trong danh sách hiện hành
    private function approver(string $value, Collection $approvers): string
    {
        if ($approvers->isEmpty()) {
            return __('messages.approver_unavailable');
        }
        $approverId = filter_var($value, FILTER_VALIDATE_INT);
        if ($approverId === false || $approverId < 1) {
            return __('messages.approver_required');
        }
        foreach ($approvers as $approver) {
            if ((int) $approver->id === $approverId) {
                return '';
            }
        }

        return __('messages.approver_required');
    }

    // Kiểm tra mã loại đơn
    private function type(string $value, array $leaveTypes): string
    {
        foreach (array_keys($leaveTypes) as $code) {
            if ($value === (string) $code) {
                return '';
            }
        }

        return __('messages.leave_type_required');
    }

    // Kiểm tra định dạng, lịch và ngày tối thiểu
    private function date(string $value, string $label, string $minimumDate): string
    {
        if ($value === '') {
            return __('messages.date_required', ['label' => mb_strtolower($label, 'UTF-8')]);
        }
        $parts = explode('-', $value);
        if (count($parts) !== 3
            || strlen($parts[0]) !== 4 || strlen($parts[1]) !== 2 || strlen($parts[2]) !== 2
            || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2])
            || ! checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return __('messages.date_invalid', ['label' => $label]);
        }
        if ($minimumDate !== '' && $value < $minimumDate) {
            return __('messages.date_in_past', ['label' => $label]);
        }

        return '';
    }

    // Kiểm tra tên, dung lượng và nội dung của file
    public function validateAttachment(string $name, string $data): string
    {
        if ($name === '' || ! mb_check_encoding($name, 'UTF-8')
            || mb_strlen($name, 'UTF-8') > AppConstants::ATTACHMENT_NAME_MAX_LENGTH) {
            return __('messages.attachment_name_invalid', ['max' => AppConstants::ATTACHMENT_NAME_MAX_LENGTH]); // Tên phải hợp lệ, không có ký tự điều khiển và không quá giới hạn ký tự
        }
        if ($data === '') {
            return __('messages.attachment_empty'); // Từ chối tệp trống
        }
        if (strlen($data) > AppConstants::ATTACHMENT_MAX_MB * 1024 * 1024) {
            return __('messages.attachment_too_large', ['size' => AppConstants::ATTACHMENT_MAX_MB]); // Từ chối tệp quá lớn
        }
        $allowedTypes = AppConstants::ATTACHMENT_ALLOWED_TYPES;
        $extension    = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! isset($allowedTypes[$extension])) {
            return __('messages.attachment_type_invalid'); // Từ chối loại tệp không được phép
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
        if (! in_array($mime, $allowedTypes[$extension], true)) {
            return __('messages.attachment_content_invalid'); // Từ chối nội dung tệp không hợp lệ
        }
        if ($extension !== 'pdf' && @getimagesizefromstring($data) === false) {
            return __('messages.attachment_image_invalid'); // Từ chối tệp hình ảnh không hợp lệ
        }
        if ($extension === 'pdf' && substr($data, 0, 5) !== '%PDF-') {
            return __('messages.attachment_pdf_invalid'); // Từ chối tệp PDF không hợp lệ
        }

        return '';
    }
}
