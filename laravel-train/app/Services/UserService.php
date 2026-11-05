<?php

namespace App\Services;

use App\Constants\UserConstants;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserService
{
    public function __construct(private UserValidationService $validation) {}

    // Tạo người dùng trong transaction
    public function save(int $userId, array $values): array
    {
        $options = [];
        try {
            DB::transaction(function () use ($userId, $values, &$options): void {
                $currentUser = User::query()->whereKey($userId)->lockForUpdate()->first();
                if ($currentUser === null || $currentUser->status !== User::STATUS_ACTIVE) {
                    throw new AuthenticationException;
                }
                Gate::forUser($currentUser)->authorize('create', User::class);

                $userTypes = [
                    User::TYPE_USER    => UserConstants::USER_TYPES[User::TYPE_USER],
                    User::TYPE_MANAGER => UserConstants::USER_TYPES[User::TYPE_MANAGER],
                ];
                $departments = UserConstants::DEPARTMENTS;
                if ($currentUser->user_type === User::TYPE_MANAGER) {
                    $userTypes = [User::TYPE_USER => UserConstants::USER_TYPES[User::TYPE_USER]];
                    $departments = [$currentUser->department => UserConstants::DEPARTMENTS[$currentUser->department]];
                }
                $options = ['user_types' => $userTypes, 'departments' => $departments];

                $errors = $this->validation->fields($values, $userTypes, $departments, checkDuplicates: false); // Kiểm tra lại các trường
                if ($errors === []) {
                    $errors = $this->validation->duplicates($values);
                }
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                $newUser = new User;
                $newUser->username = $values['username'];
                $newUser->full_name = $values['full_name'];
                $newUser->password_hash = md5($values['password']);
                $newUser->email = $values['email'] === '' ? null : $values['email'];
                $newUser->birth_date = $values['birth_date'] === '' ? null : $values['birth_date'];
                $newUser->user_type = (int) $values['user_type'];
                $newUser->department = (int) $values['department'];
                $newUser->status = User::STATUS_ACTIVE;
                $newUser->created_at = now()->format('Y-m-d H:i:s');
                $newUser->update_at = null;
                $newUser->save();
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
                if ($currentUser === null || $currentUser->status !== User::STATUS_ACTIVE) {
                    throw new AuthenticationException;
                }

                foreach ($targetUserIds as $targetUserId) {
                    $targetUser = $currentUser;
                    if ($targetUserId !== $userId) {
                        $targetUser = User::query()->whereKey($targetUserId)->lockForUpdate()->first();
                    }
                    abort_if($targetUser === null, 404);
                    Gate::forUser($currentUser)->authorize('manage', $targetUser); // Kiem tra quyền quản lý người dùng mục tiêu

                    $status = $lock ? User::STATUS_LOCKED : User::STATUS_ACTIVE; // lock = true là khóa, lock = false là mở khóa
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
