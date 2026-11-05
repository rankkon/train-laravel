<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

class UserDraftService
{
    // Chuẩn bị bản nháp tạo người dùng
    public function prepare(Request $request, array $defaultValues): array
    {
        $session = $request->session();
        if ($request->isMethod('get') && ! $session->get('user_create_resume')) { // Mở form mới sẽ xóa nháp
            $this->clear($session);
        }
        $session->forget('user_create_resume');

        $draft = $session->get('user_create_draft');
        if (! is_array($draft) || ($draft['user_id'] ?? null) !== $request->user()->id // Chỉ dùng nháp thuộc tài khoản hiện tại
            || ($draft['edited_user_id'] ?? null) !== 0) {
            $this->clear($session);
            $draft = [
                'user_id'        => $request->user()->id,
                'edited_user_id' => 0,
                'initial_values' => $defaultValues,
                'values'         => $defaultValues,
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
