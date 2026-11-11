<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('users:reset-password {username}')]
#[Description('Đặt lại mật khẩu tài khoản bằng bcrypt')]
class ResetUserPassword extends Command
{
    // Nhận mật khẩu và đặt lại mật khẩu mới
    public function handle(): int
    {
        $user = User::where('username', $this->argument('username'))->first();
        if (! $user) {
            $this->error('Không tìm thấy tài khoản.');

            return self::FAILURE;
        }

        $data = Validator::make(['password' => $this->secret('Mật khẩu mới', false)], [
            'password' => ['required', Password::min(8)->letters()->numbers()->symbols()],
        ])->validate();
        $user->update(['password_hash' => Hash::make($data['password']), 'update_at' => now()]);
        $this->info('Đã đặt lại mật khẩu.');

        return self::SUCCESS;
    }
}
