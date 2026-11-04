<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserService
{
    // Khóa hoặc mở khóa người dùng trong transaction
    public function changeStatus(int $userId, int $targetUserId, bool $lock): void
    {
        try {
            DB::transaction(function () use ($userId, $targetUserId, $lock): void {
                $currentUser = User::query()->whereKey($userId)->lockForUpdate()->first();
                if ($currentUser === null || $currentUser->status !== User::STATUS_ACTIVE) {
                    throw new AuthenticationException;
                }

                $targetUser = $currentUser;
                if ($targetUserId !== $userId) {
                    $targetUser = User::query()->whereKey($targetUserId)->lockForUpdate()->first();
                }
                abort_if($targetUser === null, 404);
                Gate::forUser($currentUser)->authorize('manage', $targetUser); // Kiem tra quyền quản lý người dùng mục tiêu

                $status = $lock ? User::STATUS_LOCKED : User::STATUS_ACTIVE; // lock = true là khóa, lock = false là mở khóa
                User::query()->whereKey($targetUserId)->update(['status' => $status]);
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
            $pdo = $connection->getPdo();
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
}
