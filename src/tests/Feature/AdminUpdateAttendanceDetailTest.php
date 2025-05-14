<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;
use App\Models\AttendanceApplication;

class AdminUpdateAttendanceDetailTest extends TestCase
{
    //13
    // 勤怠詳細画面に表示されるデータが選択したものになっている
    public function test_attendance_detail_display_correct_data()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // ユーザーを作成
        $user = User::factory()->create(['name' => 'ユーザーA']);

        // 勤怠データを作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 管理者が勤怠詳細画面にアクセス
        $response = $this->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);

        // 勤怠データが正しく表示されているか確認
        $response->assertSee('ユーザーA');
        $response->assertSee($attendance->timestamp->format('H:i'));
    }

    // 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_admin_attendance_update_invalid_time()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create(['name' => 'ユーザーA']);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        $response = $this->post('/admin/attendance/update/' . $attendance->id, [
            'clock_in' => now()->addHours(2)->format('H:i'),
            'clock_out' => now()->format('H:i'),
            'note' => 'テスト用',
            'is_correction' => true,
        ]);

        $response->assertSessionHasErrors('clock_in');

        $this->assertEquals(
            '出勤時間もしくは退勤時間が不適切な値です。',
            session('errors')->first('clock_in')
        );
    }

    // 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_admin_attendance_update_invalid_break_time()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create(['name' => 'ユーザーA']);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        $response = $this->post('/admin/attendance/update/' . $attendance->id, [
            'clock_in' => now()->format('H:i'),
            'clock_out' => now()->addHours(2)->format('H:i'),
            'break_start_1' => now()->addHours(3)->format('H:i'),
            'break_end_1' => now()->addHours(1)->format('H:i'),
            'note' => 'テスト用',
            'is_correction' => true,
        ]);

        $response->assertSessionHasErrors('break_start_1');

        $this->assertEquals(
            '休憩時間が勤務時間外です。',
            session('errors')->first('break_start_1')
        );
    }

    // 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_admin_attendance_update_invalid_break_end_time()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create(['name' => 'ユーザーA']);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        $response = $this->post('/admin/attendance/update/' . $attendance->id, [
            'clock_in' => now()->format('H:i'),
            'clock_out' => now()->addHours(2)->format('H:i'),
            'break_start_1' => now()->addHours(1)->format('H:i'),
            'break_end_1' => now()->addHours(3)->format('H:i'),
            'note' => 'テスト用',
            'is_correction' => true,
        ]);

        $response->assertSessionHasErrors('break_end_1');

        $this->assertEquals(
            '休憩時間が勤務時間外です。',
            session('errors')->first('break_end_1')
        );
    }

    // 備考欄が未入力の場合のエラーメッセージが表示される
    public function test_admin_attendance_update_empty_note()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create(['name' => 'ユーザーA']);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        $response = $this->post('/admin/attendance/update/' . $attendance->id, [
            'clock_in' => now()->format('H:i'),
            'clock_out' => now()->addHours(2)->format('H:i'),
            'note' => '',
            'is_correction' => true,
        ]);

        $response->assertSessionHasErrors('note');

        $this->assertEquals(
            '備考を記入してください。',
            session('errors')->first('note')
        );
    }
}
