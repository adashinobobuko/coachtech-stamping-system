<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;
use App\Models\AttendanceApplication;

class AdminApplicationTest extends TestCase
{
    use RefreshDatabase;

    // 15
    // 承認待ちの修正申請が全て表示されているかどうか
    public function test_admin_can_view_all_applications()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
    
        $user = User::factory()->create();
    
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);
    
        $application = AttendanceApplication::factory()->create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'status' => '承認待ち',
            'note' => '出勤：09:00 / 退勤：17:00 / 備考：テスト用',
            'old_time' => now(),
            'type' => '修正申請',
            'event_type' => '複数申請',
        ]);
    
        $response = $this->get('/admin/stamp_correction_request/list?status=承認待ち');
        $response->assertStatus(200);
        $response->assertSee('テスト用');
        $response->assertSee($user->name);
    }
    
    // 承認済みの修正申請が全て表示されているかどうか
    public function test_admin_can_view_approved_applications()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
    
        $user = User::factory()->create();
    
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);
    
        $application = AttendanceApplication::factory()->create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'status' => '承認',
            'note' => '出勤：09:00 / 退勤：17:00 / 備考：テスト用',
            'old_time' => now(),
            'type' => '修正申請',
            'event_type' => '複数申請',
        ]);
    
        $response = $this->get('/admin/stamp_correction_request/list?status=承認');
        $response->assertStatus(200);
        $response->assertSee('テスト用');
        $response->assertSee($user->name);
    }

    // 修正申請の詳細内容が正しく表示されているかどうか
    public function test_admin_can_view_application_detail()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
    
        $user = User::factory()->create();

        $oldTime = now()->setTime(9, 0); // ← ここが必要！
    
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);
    
        $application = AttendanceApplication::factory()->create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'status' => '承認待ち',
            'note' => '出勤：09:00 / 退勤：17:00 / 備考：テスト用',
            'old_time' => now(),
            'type' => '修正申請',
            'event_type' => '複数申請',
        ]);
    
        $response = $this->get('/admin/stamp_correction_request/approve/' . $application->id);
        $response->assertStatus(200);

        $response->assertSee($oldTime->format('H:i'));
        $response->assertSee('テスト用');
    }

    // 修正申請の承認処理が正しく行われるかどうか
    public function test_admin_can_see_application_as_approved_after_approval()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
    
        $user = User::factory()->create();
    
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);
    
        $application = AttendanceApplication::factory()->create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'status' => '承認待ち',
            'note' => '備考：承認後テスト',
            'old_time' => now(),
            'type' => '修正申請',
            'event_type' => '複数申請',
        ]);
    
        // 承認処理をPOST
        // ★テストの中にルート定義は書かない！
        $response = $this->post('/admin/stamp_correction_request/approve/' . $application->id);
        $response->assertRedirect(); // リダイレクト（302）が返ってくることを確認
    
        // 再度詳細ページにアクセスし、「承認済み」表示を確認
        $detailResponse = $this->get('/admin/stamp_correction_request/approve/' . $application->id);
        $detailResponse->assertStatus(200);
    
        // 「承認済み」ボタンが表示されていることを確認
        $detailResponse->assertSee('承認済み');
    
        // 備考欄のテキストも確認（表示確認として意味がある）
        $detailResponse->assertSee('承認後テスト');
    }
}
