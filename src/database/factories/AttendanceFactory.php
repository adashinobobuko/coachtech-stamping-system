<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'clock_in',
            'timestamp' => Carbon::now(),
            'note' => null,
        ];
    }

    public function clockIn()
    {
        return $this->state([
            'type' => 'clock_in',
        ]);
    }

    public function clockOut()
    {
        return $this->state([
            'type' => 'clock_out',
        ]);
    }

    public function breakStart()
    {
        return $this->state([
            'type' => 'break_start',
        ]);
    }
    
    public function breakEnd()
    {
        return $this->state([
            'type' => 'break_end',
        ]);
    }
}
    
