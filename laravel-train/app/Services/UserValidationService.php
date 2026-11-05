<?php

namespace App\Services;

use App\Constants\UserConstants;
use App\Models\User;

class UserValidationService
{
    // Kiểm tra các trường người dùng, quyền chọn danh mục và dữ liệu trùng
    public function fields(array $values, array $userTypes, array $departments, array $errors = [], bool $checkDuplicates = true): array
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
                $message = $this->password($value);
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

        return $checkDuplicates ? $this->duplicates($values, $errors) : $errors; // Tách kiểm tra trùng thành bước riêng trong transaction
    }

    // Kiểm tra trùng tên đăng nhập và email
    public function duplicates(array $values, array $errors = []): array
    {
        // Kiểm tra trùng dữ liệu username và email khi trường hợp lệ
        if (! isset($errors['username']) && User::query()->where('username', $values['username'])->exists()) {
            $errors['username'] = __('messages.username_exists');
        }
        if (! isset($errors['email']) && $values['email'] !== ''
            && User::query()->where('email', $values['email'])->exists()) {
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
        if (strlen($value) > UserConstants::USERNAME_MAX_LENGTH) {
            return __('messages.username_too_long', ['max' => UserConstants::USERNAME_MAX_LENGTH]);
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
        if (mb_strlen($value, 'UTF-8') > UserConstants::USER_FULL_NAME_MAX_LENGTH) {
            return __('messages.user_full_name_too_long', ['max' => UserConstants::USER_FULL_NAME_MAX_LENGTH]);
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
            || mb_strlen($value, 'UTF-8') < UserConstants::USER_PASSWORD_MIN_LENGTH
            || ! preg_match('/\p{L}/u', $value)
            || ! preg_match('/[0-9]/', $value)
            || ! preg_match('/[\p{P}\p{S}]/u', $value)) {
            return __('messages.user_password_format', ['min' => UserConstants::USER_PASSWORD_MIN_LENGTH]);
        }

        return '';
    }

    // Kiểm tra định dạng email
    private function email(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (strlen($value) > UserConstants::USER_EMAIL_MAX_LENGTH) {
            return __('messages.user_email_too_long', ['max' => UserConstants::USER_EMAIL_MAX_LENGTH]);
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
