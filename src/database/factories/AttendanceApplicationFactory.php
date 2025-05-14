<?php

namespace Database\Factories;

use App\Models\AttendanceApplication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceApplicationFactory extends Factory
{
    protected $model = AttendanceApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => '承認待ち',
            'note' => '出勤：09:00 / 退勤：17:00 / 備考：テスト用',
            'old_time' => now(),
            'attendance_id' => null,
            'type' => '修正申請',
            'event_type' => '複数申請', 
        ];
    }

    public function pending()
    {
        return $this->state([
            'status' => 'pending',
        ]);
    }

    public function approved()
    {
        return $this->state([
            'status' => 'approved',
        ]);
    }
}
