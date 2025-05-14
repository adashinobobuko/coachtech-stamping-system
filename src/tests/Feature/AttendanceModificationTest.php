<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceApplication;
use App\Models\Admin;

class AttendanceModificationTest extends TestCase
{
    //出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_attendance_time_after_leave_time()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 勤怠データを作成（この時点でID=1になる想定）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 出勤・退勤時刻（退勤が出勤より前）
        $clockIn = '18:00';
        $clockOut = '17:00';

        // 一度詳細ページにアクセス（課題要件に従う）
        $response = $this->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);

        // 修正申請のPOST送信
        $response = $this->post('/attendance/update/' . $attendance->id, [
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'note' => 'テスト用',
            'is_correction' => true,
        ]);

        // バリデーションエラー確認
        $response->assertSessionHasErrors(['clock_in']);
        $this->assertEquals(
            '出勤時間もしくは退勤時間が不適切な値です。',
            session('errors')->first('clock_in')
        );
    }

    // 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_break_start_after_leave_time()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 勤怠データを作成（この時点でID=1になる想定）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 出勤・退勤時刻
        $clockIn = '09:00';
        $clockOut = '17:00';

        // 休憩開始・終了時刻（休憩開始が退勤より前）
        $breakStart = '18:00';
        $breakEnd = '19:00';

        // 一度詳細ページにアクセス（課題要件に従う）
        $response = $this->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);

        // 修正申請のPOST送信
        $response = $this->post('/attendance/update/' . $attendance->id, [
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'break_start_1' => $breakStart,
            'break_end_1' => $breakEnd,
            'note' => 'テスト用',
            'is_correction' => true,
        ]);

        // バリデーションエラー確認
        $response->assertSessionHasErrors(['break_start_1']);
        $this->assertEquals(
            '休憩時間が勤務時間外です。',
            session('errors')->first('break_start_1')
        );
    }

    // 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
    public function test_break_end_after_leave_time()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 勤怠データを作成（この時点でID=1になる想定）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 出勤・退勤時刻
        $clockIn = '09:00';
        $clockOut = '17:00';

        // 休憩開始・終了時刻（休憩終了が退勤より前）
        $breakStart = '16:00';
        $breakEnd = '18:00';

        // 一度詳細ページにアクセス（課題要件に従う）
        $response = $this->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);

        // 修正申請のPOST送信
        $response = $this->post('/attendance/update/' . $attendance->id, [
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'break_start_1' => $breakStart,
            'break_end_1' => $breakEnd,
            'note' => 'テスト用',
            'is_correction' => true,
        ]);

        // バリデーションエラー確認
        $response->assertSessionHasErrors(['break_end_1']);
        $this->assertEquals(
            '休憩時間が勤務時間外です。',
            session('errors')->first('break_end_1')
        );
    }

    // 備考欄に記入がない場合、エラーメッセージが表示される
    public function test_attendance_note_empty()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 勤怠データを作成（この時点でID=1になる想定）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 出勤・退勤時刻
        $clockIn = '09:00';
        $clockOut = '17:00';

        // 一度詳細ページにアクセス（課題要件に従う）
        $response = $this->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);

        // 修正申請のPOST送信
        $response = $this->post('/attendance/update/' . $attendance->id, [
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'note' => '',
            'is_correction' => true,
        ]);

        // バリデーションエラー確認
        $response->assertSessionHasErrors(['note']);
        $this->assertEquals(
            '備考を記入してください。',
            session('errors')->first('note')
        );
    }

    // 修正申請処理が実行される
    public function test_attendance_modification_success()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 勤怠データを作成（この時点でID=1になる想定）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 出勤・退勤時刻
        $clockIn = '09:00';
        $clockOut = '17:00';

        // 一度詳細ページにアクセス（課題要件に従う）
        $response = $this->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);

        // 修正申請のPOST送信
        $response = $this->post('/attendance/update/' . $attendance->id, [
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'note' => 'テスト用',
            'is_correction' => true,
        ]);

        // 修正申請成功の確認
        $response->assertSessionHas('success');
        $this->assertStringContainsString(
            '修正申請が完了しました。',
            session('success')
        );

        $this->assertDatabaseHas('attendance_applications', [
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'status' => '承認待ち',
        ]);
    }

    // 「承認待ち」にログインユーザーが行った申請が全て表示されている
    public function test_attendance_modification_list()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 勤怠データを作成（この時点でID=1になる想定）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 修正申請を作成
        $application = AttendanceApplication::factory()->create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'status' => '承認待ち',
            'type' => '修正申請',
            'event_type' => '複数申請',
            'note' => '出勤：09:00 / 退勤：17:00 / 備考：テスト用',
            'old_time' => now(),
        ]);

        // 一度申請一覧ページにアクセス（課題要件に従う）
        $response = $this->get('/stamp_correction_request/list');
        $response->assertStatus(200);

        // 申請が表示されていることを確認
        $response->assertSee('テスト用');
    }

    // 「承認済み」に管理者が承認した修正申請が全て表示されている
    public function test_attendance_modification_approved_list()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $staff = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $staff->id,
        ]);

        AttendanceApplication::factory()->create([
            'attendance_id' => $attendance->id,
            'user_id' => $staff->id,
            'status' => '承認',
            'type' => '修正申請',
            'event_type' => '複数申請',
            'note' => '出勤：09:00 / 退勤：17:00 / 備考：テスト用',
            'old_time' => now(),
        ]);

        $response = $this->get('/admin/stamp_correction_request/list'); // 管理者用ルート
        $response->assertStatus(200);
        $response->assertSee('テスト用');
    }

    // 各申請の「詳細」を押下すると申請詳細画面に遷移する
    public function test_attendance_modification_detail()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 勤怠データを作成（この時点でID=1になる想定）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 修正申請を作成
        $application = AttendanceApplication::factory()->create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'status' => '承認待ち',
            'type' => '修正申請',
            'event_type' => '複数申請',
            'note' => '出勤：09:00 / 退勤：17:00 / 備考：テスト用',
            'old_time' => now(),
        ]);

        // 一度詳細ページにアクセス（課題要件に従う）
        $response = $this->get('/application/detail/' . $application->id);
        $response->assertStatus(200);

        // 申請が表示されていることを確認
        $response->assertSee('テスト用');
    }
}
