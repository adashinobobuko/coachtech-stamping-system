<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    // 9
    // 出勤一覧画面が正しく表示されるかどうか
    public function test_attendance_list_display()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        $now = Carbon::create(2025, 5, 4, 9, 0, 0); // ← 明示的に固定

        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => $now,
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'break_start',
            'timestamp' => $now->copy()->addMinutes(30),
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'break_end',
            'timestamp' => $now->copy()->addMinutes(60),
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_out',
            'timestamp' => $now->copy()->addHours(8),
        ]);

        // 出勤一覧画面にアクセス
        $response = $this->get('/attendance/list');

        // ステータスコードが200であることを確認
        $response->assertStatus(200);

        // 画面に先ほど作成した勤怠情報（出勤時刻と退勤時刻）が表示されていることを確認
        $response->assertSee($now->format('H:i'));
        $response->assertSee($now->copy()->addHours(8)->format('H:i'));
    }

    // 勤怠一覧画面に遷移したときに、当月が表示されるかどうか
    public function test_attendance_list_current_month()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // 勤怠一覧画面にアクセス
        $response = $this->get('/attendance/list');

        // ステータスコードが200であることを確認
        $response->assertStatus(200);

        // 現在の月が表示されていることを確認
        $currentMonth = now()->format('Y/m');
        $response->assertSee($currentMonth);
    }

    // 「前月」を押下した時に表示月の前月の情報が表示されるかどうか
    public function test_attendance_list_previous_month()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // 前月の年月
        $previousMonth = now()->subMonth()->format('Y/m');
        $previousMonthParam = now()->subMonth()->format('Y-m');

        // 勤怠一覧画面（前月）にアクセス
        $response = $this->get('/attendance/list?month=' . $previousMonthParam);

        // ステータスコードが200であることを確認
        $response->assertStatus(200);

        // 表示される月が「前月」であることを確認
        $response->assertSee($previousMonth);
    }

    // 「翌月」を押下した時に表示月の翌月の情報が表示されるかどうか
    public function test_attendance_list_next_month()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // 翌月の年月
        $nextMonth = now()->addMonth()->format('Y/m');
        $nextMonthParam = now()->addMonth()->format('Y-m');

        // 勤怠一覧画面（翌月）にアクセス
        $response = $this->get('/attendance/list?month=' . $nextMonthParam);

        // ステータスコードが200であることを確認
        $response->assertStatus(200);

        // 表示される月が「翌月」であることを確認
        $response->assertSee($nextMonth);
    }

    // 詳細ボタンを押した際に、詳細画面に遷移するかどうか
    public function test_attendance_list_detail_button()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // 勤怠情報を作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 勤怠一覧画面にアクセス
        $response = $this->get('/attendance/list');

        // 詳細ボタンを押下
        $response = $this->get('/attendance/' . $attendance->id);

        // ステータスコードが200であることを確認
        $response->assertStatus(200);

        // 詳細画面に遷移していることを確認
        $response->assertSee('勤怠詳細・修正申請');
    }
}
