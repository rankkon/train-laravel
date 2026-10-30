<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Tạo dữ liệu người dùng giả.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'full_name' => fake()->name(),
            'password_hash' => md5('Secret123!'),
            'email' => fake()->unique()->safeEmail(),
            'birth_date' => null,
            'user_type' => User::TYPE_USER,
            'department' => 2,
            'status' => User::STATUS_ACTIVE,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'update_at' => null,
        ];
    }

    /** Tạo tài khoản bị khóa. */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_LOCKED,
        ]);
    }
}
