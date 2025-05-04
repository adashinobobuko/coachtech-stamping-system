<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;

    class ViewAttendanceTest extends TestCase
    {
        use RefreshDatabase;

        // 4
        // 時刻が正しく表示されているか
        public function tests_retrieve_the_current_attendance_timestamp()
        {
            $fixedNow = Carbon::create(2025, 4, 30, 10, 0, 0);
            Carbon::setTestNow($fixedNow); // Laravelのnow() をこの日時に固定

            $user = User::factory()->create(['email_verified_at' => now()]);
            $this->actingAs($user, 'web');

            $response = $this->get('/attendance');
            $response->assertStatus(200);

            // 両方の時刻フォーマットを検証
            $dateString = $fixedNow->format('Y年n月j日 (D)');
            $timeString = $fixedNow->format('H:i');

            $response->assertSee($dateString);
            $response->assertSee($timeString);

            Carbon::setTestNow(); // 任意
        }

        // 5
        //勤務外の表示が正しいかどうか
        public function test_attendance_status_outside_working_hours()
        {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $this->actingAs($user, 'web');

            Attendance::factory()
                ->clockIn()
                ->create([
                    'user_id' => $user->id,
                    'timestamp' => Carbon::yesterday()->setHour(9),
                ]);     

            $response = $this->get('/attendance');
            $response->assertSee('勤務外');
        }

        //出勤中のステータスが正しいかどうか
        public function test_attendance_status_during_working_hours()
        {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $this->actingAs($user, 'web');

            // 勤務中の時間を設定
            Attendance::factory()
                ->clockIn()
                ->create([
                    'user_id' => $user->id,
                    'timestamp' => Carbon::now()->subHours(2),
                ]);

            $response = $this->get('/attendance');
            $response->assertSee('出勤中');
        }

        //休憩中のステータスが正しいかどうか
        public function test_attendance_status_during_break()
        {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $this->actingAs($user, 'web');

            $now = Carbon::now();

            // 出勤（clock_in）
            Attendance::factory()
                ->clockIn()
                ->create([
                    'user_id' => $user->id,
                    'timestamp' => $now->copy()->subHours(2),
                ]);

            // 休憩開始（break_start）
            Attendance::factory()
                ->breakStart()
                ->create([
                    'user_id' => $user->id,
                    'timestamp' => $now->copy()->subHour(),
                ]);

            $response = $this->get('/attendance');
            $response->assertSee('休憩中');
        }

        //退勤後のステータスがあっているかどうか
        public function test_attendance_status_after_working_hours()
        {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $this->actingAs($user, 'web');

            $now = Carbon::now();

            // 出勤（clock_in）
            Attendance::factory()
                ->clockIn()
                ->create([
                    'user_id' => $user->id,
                    'timestamp' => $now->copy()->subHours(2),
                ]);

            // 退勤（clock_out）
            Attendance::factory()
                ->clockOut()
                ->create([
                    'user_id' => $user->id,
                    'timestamp' => Carbon::now()->subHours(2),
                ]);

            $response = $this->get('/attendance');
            $response->assertSee('退勤済み');
        }

    }
