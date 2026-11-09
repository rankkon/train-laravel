<?php

namespace App\Services;

use App\Constants\AppConstants;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserService
{
    // Lấy danh sách vai trò và phòng ban được phép chọn khi thêm hoặc sửa người dùng
    public function formOptions(User $viewer, ?User $editedUser = null): array
    {
        $userTypes = [
            AppConstants::TYPE_USER    => AppConstants::USER_TYPES[AppConstants::TYPE_USER],
            AppConstants::TYPE_MANAGER => AppConstants::USER_TYPES[AppConstants::TYPE_MANAGER],
        ];
        $departments = AppConstants::DEPARTMENTS;
        if ($viewer->user_type === AppConstants::TYPE_MANAGER) { // Manager chỉ được tạo người dùng thuộc phong ban của mình
            $userTypes   = [AppConstants::TYPE_USER => AppConstants::USER_TYPES[AppConstants::TYPE_USER]];
            $departments = [$viewer->department => AppConstants::DEPARTMENTS[$viewer->department]];
        }
        if ($editedUser !== null && $editedUser->user_type === AppConstants::TYPE_ADMIN) {
            $userTypes = [AppConstants::TYPE_ADMIN => AppConstants::USER_TYPES[AppConstants::TYPE_ADMIN]]; // Khi sửa admin, chỉ giữ lựa chọn vai trò admin
        }

        return ['user_types' => $userTypes, 'departments' => $departments];
    }

    // Thêm hoặc cập nhật người dùng trong transaction
    public function save(int $userId, array $values, int $editedUserId = 0): array
    {
        $options = [];
        try {
            DB::transaction(function () use ($userId, $values, $editedUserId, &$options): void {
                $currentUser = User::query()->whereKey($userId)->lockForUpdate()->first();
                if ($currentUser === null || $currentUser->status !== AppConstants::STATUS_ACTIVE) {
                    throw new AuthenticationException;
                }
                $editedUser = null;
                if ($editedUserId > 0) {
                    $editedUser = $currentUser;
                    if ($editedUserId !== $userId) {
                        $editedUser = User::query()->whereKey($editedUserId)->lockForUpdate()->first();
                    }
                    abort_if($editedUser === null, 404);
                    Gate::forUser($currentUser)->authorize('manage', $editedUser);
                    $values['username'] = $editedUser->username;
                    if ($editedUser->user_type === AppConstants::TYPE_ADMIN) {
                        $values['user_type'] = (string) AppConstants::TYPE_ADMIN;
                    }
                } else {
                    Gate::forUser($currentUser)->authorize('create', User::class);
                }

                // kiểm tra lại danh mục theo quyền hiện tại trong transaction
                $options = $this->formOptions($currentUser, $editedUser);
                $errors  = $this->validateFields($values, $options['user_types'], $options['departments'], checkDuplicates: false, editedUserId: $editedUserId); // Kiểm tra lại các trường
                if ($errors === []) {
                    $errors = $this->validateDuplicates($values, editedUserId: $editedUserId);
                }
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                if ($editedUser !== null) {
                    User::query()->whereKey($editedUserId)->update([
                        'full_name'  => $values['full_name'],
                        'email'      => $values['email'] === '' ? null : $values['email'],
                        'birth_date' => $values['birth_date'] === '' ? null : $values['birth_date'],
                        'user_type'  => (int) $values['user_type'],
                        'department' => (int) $values['department'],
                        'update_at'  => now()->format('Y-m-d H:i:s'),
                    ]);
                    if ($values['password'] !== '') {
                        User::query()->whereKey($editedUserId)->update(['password_hash' => md5($values['password'])]);
                    }
                } else {
                    $newUser                = new User;
                    $newUser->username      = $values['username'];
                    $newUser->full_name     = $values['full_name'];
                    $newUser->password_hash = md5($values['password']);
                    $newUser->email         = $values['email'] === '' ? null : $values['email'];
                    $newUser->birth_date    = $values['birth_date'] === '' ? null : $values['birth_date'];
                    $newUser->user_type     = (int) $values['user_type'];
                    $newUser->department    = (int) $values['department'];
                    $newUser->status        = AppConstants::STATUS_ACTIVE;
                    $newUser->created_at    = now()->format('Y-m-d H:i:s');
                    $newUser->update_at     = null;
                    $newUser->save();
                }
            });
        } catch (ValidationException $error) {
            return [
                'errors'  => array_map(fn (array $messages): string => $messages[0], $error->errors()),
                'options' => $options,
            ];
        } catch (Throwable $error) {
            $this->rollbackTransaction();

            throw $error;
        }

        return ['errors' => [], 'options' => $options];
    }

    // Xử lý khóa, mở khóa người dùng trong transaction
    public function changeStatus(int $userId, array $targetUserIds, bool $lock): void
    {
        try {
            DB::transaction(function () use ($userId, $targetUserIds, $lock): void {
                $currentUser = User::query()->whereKey($userId)->lockForUpdate()->first();
                if ($currentUser === null || $currentUser->status !== AppConstants::STATUS_ACTIVE) {
                    throw new AuthenticationException;
                }

                foreach ($targetUserIds as $targetUserId) {
                    $targetUser = $currentUser;
                    if ($targetUserId !== $userId) {
                        $targetUser = User::query()->whereKey($targetUserId)->lockForUpdate()->first();
                    }
                    abort_if($targetUser === null, 404);
                    Gate::forUser($currentUser)->authorize('manage', $targetUser); // Kiem tra quyền quản lý người dùng mục tiêu

                    $status = $lock ? AppConstants::STATUS_LOCKED : AppConstants::STATUS_ACTIVE; // lock = true là khóa, lock = false là mở khóa
                    User::query()->whereKey($targetUserId)->update(['status' => $status]);
                }
            });
        } catch (Throwable $error) {
            $this->rollbackTransaction();

            throw $error;
        }
    }

    private function rollbackTransaction(): void
    {
        try {
            $connection = DB::connection();
            $pdo        = $connection->getPdo();
            if ($pdo->inTransaction()) {
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                } else {
                    $pdo->rollBack();
                }
            }
        } catch (Throwable) {
            Log::error(__('messages.user_rollback_failed'));
        }
    }

    // Chuẩn bị bản nháp thêm hoặc sửa người dùng
    public function prepareDraft(Request $request, array $defaultValues, ?User $editedUser = null): array
    {
        $session = $request->session();
        if ($request->isMethod('get') && ! $session->get('user_create_resume')) { // Mở form mới sẽ xóa nháp
            $this->clearDraft($session);
        }
        $session->forget('user_create_resume');

        $editedUserId = $editedUser?->id ?? 0;
        $draft        = $session->get('user_create_draft');
        if (! is_array($draft) || ($draft['user_id'] ?? null) !== $request->user()->id // Chỉ dùng nháp thuộc tài khoản hiện tại
            || ($draft['edited_user_id'] ?? null) !== $editedUserId) {
            $this->clearDraft($session);
            $initialValues = $defaultValues;
            if ($editedUser !== null) {
                foreach (array_keys($initialValues) as $field) { // Nạp các trường dữ liệu trừ mật khẩu
                    if ($field !== 'password') {
                        $initialValues[$field] = (string) $editedUser->getRawOriginal($field);
                    }
                }
            }
            // Giữ dữ liệu ban đầu để phát hiện thay đổi
            $draft = [
                'user_id'        => $request->user()->id,
                'edited_user_id' => $editedUserId,
                'initial_values' => $initialValues,
                'values'         => $initialValues,
                'confirmed'      => false,
                'errors'         => [],
            ];
            $session->put('user_create_draft', $draft);
        }

        return $draft;
    }

    // Xóa bản nháp và các cờ xác nhận của form
    public function clearDraft(Session $session): void
    {
        $session->forget([
            'user_create_draft', 'user_create_exit', 'user_create_save_pending', 'user_create_resume',
        ]);
    }

    // Kiểm tra các trường người dùng, quyền chọn danh mục và dữ liệu trùng
    public function validateFields(array $values, array $userTypes, array $departments, array $errors = [], bool $checkDuplicates = true, int $editedUserId = 0): array
    {
        foreach ($values as $field => $value) {
            if (isset($errors[$field])) {
                continue;
            }

            $message = '';
            if ($field === 'username') {
                $message = $this->username($value);
            } elseif ($field === 'full_name') {
                $message = $this->fullName($value);
            } elseif ($field === 'password') {
                if ($editedUserId === 0 || $value !== '') {
                    $message = $this->password($value);
                }
            } elseif ($field === 'email') {
                $message = $this->email($value);
            } elseif ($field === 'birth_date') {
                $message = $this->birthDate($value);
            } elseif ($field === 'user_type' && ! isset($userTypes[$value])) {
                $message = __('messages.user_type_invalid');
            } elseif ($field === 'department' && ! isset($departments[$value])) {
                $message = __('messages.user_department_invalid');
            }

            if ($message !== '') {
                $errors[$field] = $message;
            }
        }

        return $checkDuplicates ? $this->validateDuplicates($values, $errors, $editedUserId) : $errors; // Tách kiểm tra trùng thành bước riêng trong transaction
    }

    // Kiểm tra trùng tên đăng nhập và email
    public function validateDuplicates(array $values, array $errors = [], int $editedUserId = 0): array
    {
        // Kiểm tra trùng dữ liệu username và email khi trường hợp lệ
        if ($editedUserId === 0 && ! isset($errors['username']) && User::query()->where('username', $values['username'])->exists()) {
            $errors['username'] = __('messages.username_exists');
        }
        if (! isset($errors['email']) && $values['email'] !== ''
            && User::query()->where('email', $values['email'])->where('id', '!=', $editedUserId)->exists()) {
            $errors['email'] = __('messages.user_email_exists');
        }

        return $errors;
    }

    // Kiểm tra tên đăng nhập
    private function username(string $value): string
    {
        if ($value === '') {
            return __('messages.username_required');
        }
        if (! preg_match('/\A[A-Za-z0-9]+\z/', $value)) {
            return __('messages.username_format');
        }
        if (strlen($value) > AppConstants::USERNAME_MAX_LENGTH) {
            return __('messages.username_too_long', ['max' => AppConstants::USERNAME_MAX_LENGTH]);
        }

        return '';
    }

    // Kiểm tra tên người dùng
    private function fullName(string $value): string
    {
        if (trim($value, ' ') === '') {
            return __('messages.user_full_name_required');
        }
        if (! preg_match('/\A(?:\p{L}\p{M}*| )+\z/u', $value)) {
            return __('messages.user_full_name_format');
        }
        if (mb_strlen($value, 'UTF-8') > AppConstants::USER_FULL_NAME_MAX_LENGTH) {
            return __('messages.user_full_name_too_long', ['max' => AppConstants::USER_FULL_NAME_MAX_LENGTH]);
        }

        return '';
    }

    // Kiểm tra mật khẩu
    private function password(string $value): string
    {
        if ($value === '') {
            return __('messages.user_password_required');
        }
        if (! mb_check_encoding($value, 'UTF-8') // Mật khẩu phải đủ độ dài, có chữ cái, số và ký tự đặc biệt
            || mb_strlen($value, 'UTF-8') < AppConstants::USER_PASSWORD_MIN_LENGTH
            || ! preg_match('/\p{L}/u', $value)
            || ! preg_match('/[0-9]/', $value)
            || ! preg_match('/[\p{P}\p{S}]/u', $value)) {
            return __('messages.user_password_format', ['min' => AppConstants::USER_PASSWORD_MIN_LENGTH]);
        }

        return '';
    }

    // Kiểm tra định dạng email
    private function email(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (strlen($value) > AppConstants::USER_EMAIL_MAX_LENGTH) {
            return __('messages.user_email_too_long', ['max' => AppConstants::USER_EMAIL_MAX_LENGTH]);
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return __('messages.user_email_format');
        }

        return '';
    }

    // Kiểm tra ngày sinh
    private function birthDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $parts = explode('-', $value);
        if (count($parts) !== 3
            || strlen($parts[0]) !== 4 || strlen($parts[1]) !== 2 || strlen($parts[2]) !== 2
            || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2])
            || ! checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return __('messages.date_invalid', ['label' => 'Ngày sinh']);
        }
        if ($value >= now()->toDateString()) { // Ngày sinh phải trước ngày hiện tại
            return __('messages.user_birth_date_must_be_past');
        }

        return '';
    }
}
