<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    /**
     * Tạo dữ liệu đơn giả.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'approver_id' => User::factory()->state(['user_type' => User::TYPE_MANAGER]),
            'title' => fake()->sentence(),
            'content' => null,
            'leave_type' => LeaveRequest::TYPE_NGHIPHEP,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => LeaveRequest::STATUS_NEW,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'approved_at' => null,
            'update_at' => null,
            'delete_at' => null,
            'cancel_reason' => null,
            'attachment_original_name' => 'test-attachment.pdf',
            'attachment_path' => 'uploads/test-attachment.pdf',
        ];
    }
}
