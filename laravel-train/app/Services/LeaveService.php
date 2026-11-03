<?php

namespace App\Services;

use App\Constants\LeaveConstants;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LeaveService
{
    private string $uploadDirectory;

    public function __construct(private LeaveValidationService $validation, ?string $uploadDirectory = null)
    {
        $this->uploadDirectory = $uploadDirectory ?? base_path('../../train-php/uploads');
    }

    // Lưu đơn mới và file đính kèm trong transaction
    public function save(int $userId, array $values, ?array $attachment): array
    {
        $fullPath = null;

        try {
            DB::transaction(function () use ($userId, $values, $attachment, &$fullPath): void {
                $users = User::query()
                    ->whereIn('id', [$userId, (int) $values['approver_id']])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $currentUser = $users->get($userId);
                if ($currentUser === null || $currentUser->status !== User::STATUS_ACTIVE) {
                    throw new AuthenticationException;
                }

                $errors = $this->validation->fields(
                    $values, $currentUser->leaveApprovers(), LeaveConstants::LEAVE_TYPES, now()->toDateString(),
                );
                if (! $attachment || (int) $attachment['user_id'] !== $userId) {
                    $fileError = __('messages.attachment_select_again');
                } else {
                    $fileError = $this->validation->attachment($attachment['name'], $attachment['data']);
                }
                if ($fileError !== '') {
                    $errors['attachment'] = $fileError;
                }
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors); // Dừng transaction và trả về lỗi nếu có lỗi dữ liệu
                }

                if (! is_dir($this->uploadDirectory)) { // Yêu cầu thư mục upload tồn tại
                    throw new RuntimeException(__('messages.upload_directory_missing'));
                }

                $extension = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));
                $filename = bin2hex(random_bytes(LeaveConstants::ATTACHMENT_RANDOM_BYTES)).'.'.$extension;
                $fullPath = $this->uploadDirectory.DIRECTORY_SEPARATOR.$filename;
                $writtenBytes = @file_put_contents($fullPath, $attachment['data'], LOCK_EX);
                if ($writtenBytes !== strlen($attachment['data'])) {
                    throw new RuntimeException(__('messages.attachment_write_failed'));
                }

                LeaveRequest::query()->insert([
                    'user_id'                  => $userId,
                    'title'                    => $values['title'],
                    'content'                  => $values['content'],
                    'approver_id'              => (int) $values['approver_id'],
                    'leave_type'               => (int) $values['leave_type'],
                    'start_date'               => $values['start_date'],
                    'end_date'                 => $values['end_date'],
                    'status'                   => LeaveRequest::STATUS_NEW,
                    'created_at'               => now()->format('Y-m-d H:i:s'),
                    'attachment_original_name' => $attachment['name'],
                    'attachment_path'          => 'uploads/'.$filename,
                ]);
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

        return [];
    }

    // Duyệt hoặc hủy đơn trong transaction
    public function process(int $userId, int $leaveId, string $action, string $reason): void
    {
        try {
            DB::transaction(function () use ($userId, $leaveId, $action, $reason): void {
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
                if ($currentUser === null || $currentUser->status !== User::STATUS_ACTIVE) {
                    throw new AuthenticationException;
                }

                $owner = $users->get($leave->user_id);
                abort_if($owner === null, 404);
                $leave->setRelation('user', $owner);
                abort_unless(Gate::forUser($currentUser)->allows('process', $leave), 409);

                $processedAt = now();
                if ($action === 'approve') {
                    $changes = [
                        'status'      => LeaveRequest::STATUS_APPROVED,
                        'update_at'   => $processedAt->format('Y-m-d H:i:s'),
                        'approved_at' => $processedAt->toDateString(),
                    ];
                } else {
                    $changes = [
                        'status'        => LeaveRequest::STATUS_CANCELLED,
                        'delete_at'     => $processedAt->format('Y-m-d H:i:s'),
                        'cancel_reason' => $reason,
                    ];
                }

                LeaveRequest::query()
                    ->whereKey($leaveId)
                    ->where('status', LeaveRequest::STATUS_NEW)
                    ->update($changes);
            });
        } catch (Throwable $error) {
            $this->rollbackTransaction();

            throw $error;
        }
    }

    // Rollback transaction nếu có lỗi xảy ra
    private function rollbackTransaction(): void
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
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
}
