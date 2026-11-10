<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    // Lấy dữ liệu gốc hoặc dùng dữ liệu khởi tạo khi  mở form
    protected function originalFormValues(Request $request, array $values): array
    {
        $originalValues = $request->isMethod('post') ? $request->post('original_values') : null;
        if ($originalValues === null) {
            return $values;
        }
        abort_unless(is_array($originalValues), 400);
        foreach (array_keys($values) as $field) { // Chỉ nhận các trường đã khai báo
            abort_unless(isset($originalValues[$field]) && is_string($originalValues[$field]), 400);
            $values[$field] = $originalValues[$field];
        }

        return $values;
    }

    // So sánh giữ liệu hiện tại với dữ liệu lúc mở form
    protected function formHasChanges(array $values, array $originalValues): bool
    {
        foreach ($values as $field => $value) {
            if (str_replace(["\r\n", "\r"], "\n", $value) !== str_replace(["\r\n", "\r"], "\n", $originalValues[$field])) {
                return true;
            }
        }

        return false;
    }
}
