<?php

namespace App\Services;

use App\Constants\LeaveConstants;
use finfo;
use Illuminate\Support\Collection;

class LeaveValidationService
{
    /** Kiểm tra các trường và thứ tự ngày. */
    public function fields(array $values, Collection $approvers, array $leaveTypes, string $minimumDate): array
    {
        $messages = [
            'title' => $this->title($values['title']),
            'content' => $this->content($values['content']),
            'approver_id' => $this->approver($values['approver_id'], $approvers),
            'leave_type' => $this->type($values['leave_type'], $leaveTypes),
            'start_date' => $this->date($values['start_date'], 'Ngày bắt đầu', $minimumDate),
            'end_date' => $this->date($values['end_date'], 'Ngày kết thúc', $minimumDate),
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

    /** Kiểm tra tiêu đề bắt buộc và giới hạn ký tự. */
    private function title(string $title): string
    {
        if (! mb_check_encoding($title, 'UTF-8')) {
            return __('messages.leave_title_encoding');
        }
        if (! preg_match('/[^\s\p{Z}]/u', $title)) {
            return __('messages.leave_title_required');
        }
        if (mb_strlen($title, 'UTF-8') > LeaveConstants::TITLE_MAX_LENGTH) {
            return __('messages.leave_title_too_long', ['max' => LeaveConstants::TITLE_MAX_LENGTH]);
        }

        return '';
    }

    /** Kiểm tra định dạng và dung lượng nội dung. */
    private function content(string $content): string
    {
        if (! mb_check_encoding($content, 'UTF-8')) {
            return __('messages.leave_content_encoding');
        }
        if (strlen($content) > LeaveConstants::CONTENT_MAX_BYTES) {
            return __('messages.leave_content_too_long');
        }

        return '';
    }

    /** Kiểm tra người duyệt trong danh sách hiện hành. */
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

    /** Kiểm tra mã loại đơn. */
    private function type(string $value, array $leaveTypes): string
    {
        foreach ($leaveTypes as $code => $name) {
            if ($value === (string) $code) {
                return '';
            }
        }

        return __('messages.leave_type_required');
    }

    /** Kiểm tra định dạng, lịch và ngày tối thiểu. */
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

    /** Kiểm tra tên, dung lượng và nội dung thật của file. */
    public function attachment(string $name, string $data): string
    {
        if ($name === '' || ! mb_check_encoding($name, 'UTF-8')
            || mb_strlen($name, 'UTF-8') > LeaveConstants::ATTACHMENT_NAME_MAX_LENGTH
            || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            return __('messages.attachment_name_invalid', ['max' => LeaveConstants::ATTACHMENT_NAME_MAX_LENGTH]);
        }
        if ($data === '') {
            return __('messages.attachment_empty');
        }
        if (strlen($data) > LeaveConstants::ATTACHMENT_MAX_MB * 1024 * 1024) {
            return __('messages.attachment_too_large', ['size' => LeaveConstants::ATTACHMENT_MAX_MB]);
        }
        $allowedTypes = LeaveConstants::ATTACHMENT_ALLOWED_TYPES;
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! isset($allowedTypes[$extension])) {
            return __('messages.attachment_type_invalid');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
        if (! in_array($mime, $allowedTypes[$extension], true)) {
            return __('messages.attachment_content_invalid');
        }
        if ($extension !== 'pdf' && @getimagesizefromstring($data) === false) {
            return __('messages.attachment_image_invalid');
        }
        if ($extension === 'pdf' && substr($data, 0, 5) !== '%PDF-') {
            return __('messages.attachment_pdf_invalid');
        }

        return '';
    }
}
