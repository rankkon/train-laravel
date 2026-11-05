<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

class UserDraftService
{
    // Chuẩn bị bản nháp thêm hoặc sửa người dùng
    public function prepare(Request $request, array $defaultValues, ?User $editedUser = null): array
    {
        $session = $request->session();
        if ($request->isMethod('get') && ! $session->get('user_create_resume')) { // Mở form mới sẽ xóa nháp
            $this->clear($session);
        }
        $session->forget('user_create_resume');

        $editedUserId = $editedUser?->id ?? 0;
        $draft = $session->get('user_create_draft');
        if (! is_array($draft) || ($draft['user_id'] ?? null) !== $request->user()->id // Chỉ dùng nháp thuộc tài khoản hiện tại
            || ($draft['edited_user_id'] ?? null) !== $editedUserId) {
            $this->clear($session);
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
    public function clear(Session $session): void
    {
        $session->forget([
            'user_create_draft', 'user_create_exit', 'user_create_save_pending', 'user_create_resume',
        ]);
    }
}
