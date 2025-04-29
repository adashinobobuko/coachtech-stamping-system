<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use Carbon\Carbon;

class TestUserAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 1;

        // 3月分
        $marchStart = Carbon::create(2025, 3, 1);
        $marchEnd = Carbon::create(2025, 3, 31);
        $this->seedAttendance($userId, $marchStart, $marchEnd);

        // 4月分
        $aprilStart = Carbon::create(2025, 4, 1);
        $aprilEnd = Carbon::now(); // 今日まで
        $this->seedAttendance($userId, $aprilStart, $aprilEnd);
    }

    private function seedAttendance($userId, Carbon $start, Carbon $end)
    {
        for ($date = $start; $date->lte($end); $date->addDay()) {
            // 土日は飛ばす（平日だけ）
            if ($date->isWeekend()) {
                continue;
            }

            // 出勤
            Attendance::create([
                'user_id' => $userId,
                'type' => 'clock_in',
                'timestamp' => $date->copy()->setTime(9, 0, 0),
            ]);

            // 休憩開始
            Attendance::create([
                'user_id' => $userId,
                'type' => 'break_start',
                'timestamp' => $date->copy()->setTime(12, 0, 0),
            ]);

            // 休憩終了
            Attendance::create([
                'user_id' => $userId,
                'type' => 'break_end',
                'timestamp' => $date->copy()->setTime(13, 0, 0),
            ]);

            // 退勤
            Attendance::create([
                'user_id' => $userId,
                'type' => 'clock_out',
                'timestamp' => $date->copy()->setTime(18, 0, 0),
            ]);
        }
    }
}
