<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;

class SubmitAttendanceTest extends TestCase
{
    use RefreshDatabase;
    
    // 6
    // 出勤ボタンが正しく機能するかどうか
    public function test_clock_in_button_functionality()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        //画面に出勤のボタンが表示されているか
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');

        $response = $this->post('/attendance', [
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 出勤ボタンを押した後のリダイレクト先を確認
        $response->assertStatus(302);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'type' => 'clock_in',
        ]);

        // 画面に「出勤中」と表示されることを確認
        $response = $this->get('/attendance');
        $response->assertSee('出勤中');
    }

    //出勤は1日1回しかできないかどうか
    public function test_clock_in_once_per_day()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // すでに出勤している状態を作成
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 画面に出勤のボタンが表示されているか
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    // 出勤時刻が管理画面で確認できるかどうか
    public function test_clock_in_time_visible_in_admin()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        $timestamp = now()->setSeconds(0); // 秒を揃える（または切り捨て）

        $this->post('/attendance', [
            'type' => 'clock_in',
            'timestamp' => $timestamp,
        ]);

        // 管理者としてログイン
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 管理者画面にアクセス
        $response = $this->get('/admin/attendance/list');
        $response->assertStatus(200);

        // 出勤時刻が表示されていることを確認
        $response->assertSeeText($timestamp->format('H:i'));
    }

    // 7
    // 休憩ボタンが正しく機能するかどうか
    public function test_break_start_button_functionality()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // すでに出勤している状態を作成
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 画面に休憩のボタンが表示されているか
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');

        // 休憩ボタンを押す
        $response = $this->post('/attendance', [
            'type' => 'break_start',
            'timestamp' => now(),
        ]);

        // 休憩ボタンを押した後のリダイレクト先を確認
        $response->assertStatus(302);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'type' => 'break_start',
        ]);

        // 画面に「休憩中」と表示されることを確認
        $response = $this->get('/attendance');
        $response->assertSee('休憩中');
    }

    // 休憩入りは1日に何回でもできるかどうか
    public function test_break_start_multiple_times()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // すでに出勤している状態を作成
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 休憩ボタンを押す
        $this->post('/attendance', [
            'type' => 'break_start',
            'timestamp' => now(),
        ]);

        // 画面に休憩のボタンが表示されているか
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩中');

        // 休憩戻ボタンを押す
        $response = $this->post('/attendance', [
            'type' => 'break_end',
            'timestamp' => now(),
        ]);

        // 休憩ボタンを押した後のリダイレクト先を確認
        $response->assertStatus(302);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'type' => 'break_end',
        ]);

        // 画面に「出勤中」と表示されることを確認
        $response = $this->get('/attendance');
        $response->assertSee('出勤中');
        
        // 再び休憩ボタンを押す
        $response = $this->post('/attendance', [
            'type' => 'break_start',
            'timestamp' => now(),
        ]);

        // 画面に休憩のボタンが表示されているか
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    // 休憩戻ボタンが機能するかどうか
    public function test_break_end_button_functionality()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // すでに出勤している状態を作成
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 休憩ボタンを押す
        $this->post('/attendance', [
            'type' => 'break_start',
            'timestamp' => now(),
        ]);

        // 休憩戻ボタンを押す
        $response = $this->post('/attendance', [
            'type' => 'break_end',
            'timestamp' => now(),
        ]);

        // 休憩戻ボタンを押した後のリダイレクト先を確認
        $response->assertStatus(302);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'type' => 'break_end',
        ]);

        // 画面に「出勤中」と表示されることを確認
        $response = $this->get('/attendance');
        $response->assertSee('出勤中');
    }

    // 休憩戻は1日に何回でもできるかどうか
    public function test_break_end_multiple_times()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // すでに出勤している状態を作成
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 休憩ボタンを押す
        $this->post('/attendance', [
            'type' => 'break_start',
            'timestamp' => now(),
        ]);

        // 休憩戻ボタンを押す
        $this->post('/attendance', [
            'type' => 'break_end',
            'timestamp' => now(),
        ]);

        // 画面に「出勤中」と表示されることを確認
        $response = $this->get('/attendance');
        $response->assertSee('出勤中');

        // 休憩ボタンを押す
        $this->post('/attendance', [
            'type' => 'break_start',
            'timestamp' => now(),
        ]);

        // 再び休憩戻ボタンを押す
        $response = $this->post('/attendance', [
            'type' => 'break_end',
            'timestamp' => now(),
        ]);

        // 画面に「出勤中」と表示されることを確認
        $response = $this->get('/attendance');
        $response->assertSee('出勤中');
    }

    // 休憩の時間が正しく記録されるかどうか
    public function test_break_time_recorded_correctly()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        $fixedNow = Carbon::create(2025, 5, 2, 10, 0, 0);
        Carbon::setTestNow($fixedNow);

        // clock_in → 2時間前
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => $fixedNow->copy()->subHours(2),
        ]);

        // break_start → 2分前
        $this->post('/attendance', [
            'type' => 'break_start',
            'timestamp' => $fixedNow->copy()->subMinutes(2),
        ]);

        // break_end → 今
        $this->post('/attendance', [
            'type' => 'break_end',
            'timestamp' => $fixedNow,
        ]);

        // 一覧画面で休憩時刻が表示されているか
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        Carbon::setLocale('ja');
        $response->assertSee($fixedNow->format('m/d') . '（' . $fixedNow->isoFormat('dd') . '）');
        $response->assertSee('00:02'); // 例：休憩時間1分だったとき（表示仕様に合わせて）
    }

    // 8
    // 退勤ボタンが正しく機能するかどうか
    public function test_clock_out_button_functionality()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        // すでに出勤している状態を作成
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 画面に退勤のボタンが表示されているか
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤');

        // 退勤ボタンを押す
        $response = $this->post('/attendance', [
            'type' => 'clock_out',
            'timestamp' => now(),
        ]);

        // 退勤ボタンを押した後のリダイレクト先を確認
        $response->assertStatus(302);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'type' => 'clock_out',
        ]);

        // 画面に「退勤済み」と表示されることを確認
        $response = $this->get('/attendance');
        $response->assertSee('退勤済み');
    }

    // 退勤時刻が管理画面で確認できるかどうか
    public function test_clock_out_time_visible_in_admin()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        $timestamp = now()->setSeconds(0); 
        $clockOutTime = $timestamp->copy()->addHours(8);

        // 出勤打刻
        $this->post('/attendance', [
            'type' => 'clock_in',
            'timestamp' => $timestamp,
        ]);

        // 退勤打刻
        $this->post('/attendance', [
            'type' => 'clock_out',
            'timestamp' => $clockOutTime,
        ]);

        // 管理者としてログイン
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 管理者画面にアクセス
        $response = $this->get('/admin/attendance/list');
        $response->assertStatus(200);

        // 退勤時刻が表示されていることを確認
        $response->assertSeeText($clockOutTime->format('H:i'));
    }
}
