<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\AttendanceApplication;
use App\Models\AttendanceModification;
use Carbon\Carbon;

class TestUserAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 1;
    
        $start = Carbon::create(2025, 5, 1);
        $end = Carbon::create(2025, 5, 31);
        $this->seedAttendance($userId, $start, $end);
    
        // テスト用に修正対象日を明示
        $targetDates = ['2025-05-13','2025-05-15'];
        $this->setAttendanceApplication($userId, $targetDates);

        // 修正申請の承認
        $targetDates = ['2025-05-15'];
        $this->setAttendanceModification($userId, $targetDates);
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

    public function setAttendanceApplication($userId, array $dates)
    {
        foreach ($dates as $dateString) {
            $date = Carbon::parse($dateString);
    
            if ($date->isWeekend()) continue;
    
            $attendance = Attendance::where('user_id', $userId)
                ->where('type', 'clock_in')
                ->whereDate('timestamp', $date)
                ->first();
    
            if ($attendance) {
                AttendanceApplication::create([
                    'attendance_id' => $attendance->id,
                    'user_id' => $userId,
                    'type' => '修正申請',
                    'event_type' => '複数申請',
                    'old_time' => $attendance->timestamp,
                    'new_time' => $date->copy()->setTime(8, 50),
                    'note' => '出勤：08:00 / 退勤：17:00 / 休憩1：12:00～13:00 / 備考：修正',
                    'status' => '承認',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
    
    public function setAttendanceModification($userId, array $dates)
    {
        $adminId = 1;
    
        foreach ($dates as $dateString) {
            $date = Carbon::parse($dateString);
    
            // 対象フィールド（field → DBの定義に合わせる）
            $types = [
                'clock_in' => ['old' => '08:00', 'new' => '08:30'],
                'clock_out' => ['old' => '17:00', 'new' => '17:30'],
                'break_start' => ['old' => '12:00', 'new' => '11:30'],
                'break_end' => ['old' => '13:00', 'new' => '12:30'],
            ];
    
            foreach ($types as $field => $times) {
                $attendance = Attendance::where('user_id', $userId)
                    ->where('type', $field)
                    ->whereDate('timestamp', $date)
                    ->first();
    
                if ($attendance) {
                    AttendanceModification::create([
                        'admin_id' => $adminId,
                        'user_id' => $userId,
                        'attendance_id' => $attendance->id,
                        'field' => $field,
                        'old_value' => $date->copy()->setTimeFromTimeString($times['old'])->format('H:i'),
                        'new_value' => $date->copy()->setTimeFromTimeString($times['new'])->format('H:i'),
                        'modified_at' => now(),
                    ]);
                }
            }
    
            // 備考（note）修正も追加
            $clockInAttendance = Attendance::where('user_id', $userId)
                ->where('type', 'clock_in')
                ->whereDate('timestamp', $date)
                ->first();
    
            if ($clockInAttendance) {
                AttendanceModification::create([
                    'admin_id' => $adminId,
                    'user_id' => $userId,
                    'attendance_id' => $clockInAttendance->id,
                    'field' => 'note',
                    'old_value' => null,
                    'new_value' => 'テスト備考',
                    'modified_at' => now(),
                ]);
            }
        }
    }
    
}
