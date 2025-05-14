<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    // 10
    // 勤怠詳細・修正申請画面が正しく表示されるかどうか
    public function test_attendance_detail_display()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // 勤怠情報を作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 勤怠詳細画面にアクセス
        $response = $this->get('/attendance/' . $attendance->id);

        // ステータスコードが200であることを確認
        $response->assertStatus(200);

        // 画面に勤怠情報が表示されていることを確認
        $response->assertSee($attendance->timestamp->format('H:i'));

        // 名前が表示されていることを確認
        $response->assertSee($user->name);
    }

    // 勤怠詳細・修正画面の日付が正しく表示されるかどうか
    public function test_attendance_detail_date_display()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // 勤怠情報を作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 勤怠詳細画面にアクセス
        $response = $this->get('/attendance/' . $attendance->id);

        // ステータスコードが200であることを確認
        $response->assertStatus(200);

        // 画面に日付が表示されていることを確認
        $response->assertSee($attendance->timestamp->format('Y年n月j日'));
    }

    // 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致しているかどうか
    public function test_attendance_detail_time_display()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        $clockIn = now()->setTime(9, 30);
        $clockOut = $clockIn->copy()->addHours(8);

        // 出勤と退勤を記録
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => $clockIn,
        ]);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_out',
            'timestamp' => $clockOut,
        ]);

        // 出勤打刻から詳細ページにアクセス
        $attendance = Attendance::where('user_id', $user->id)
                                ->where('type', 'clock_in')
                                ->first();

        $response = $this->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);

        $response->assertSee('name="clock_in" value="' . $clockIn->format('H:i') . '"', false);
        $response->assertSee('name="clock_out" value="' . $clockOut->format('H:i') . '"', false);
    }

    // 休憩にて記されている時間がログインユーザーの打刻と一致しているかどうか
    public function test_attendance_detail_break_time_display()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        $breakStart = now()->setTime(12, 0);
        $breakEnd = $breakStart->copy()->addHours(1);

        // 休憩を記録
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'break_start',
            'timestamp' => $breakStart,
        ]);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'break_end',
            'timestamp' => $breakEnd,
        ]);

        // 休憩開始打刻から詳細ページにアクセス
        $attendance = Attendance::where('user_id', $user->id)
                                ->where('type', 'break_start')
                                ->first();

        $response = $this->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);

        $response->assertSee('name="break_start_1" value="' . $breakStart->format('H:i') . '"', false);
        $response->assertSee('name="break_end_1" value="' . $breakEnd->format('H:i') . '"', false);
    }
}
