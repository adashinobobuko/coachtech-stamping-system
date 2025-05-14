<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;

class AdminGetUserStateTest extends TestCase
{
    use RefreshDatabase;

    // 14
    // 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できるかどうか
    public function test_admin_can_view_all_users()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 一般ユーザーを複数作成
        $users = User::factory()->count(3)->create();

        $response = $this->get('/admin/staff/list');
        $response->assertStatus(200);

        // 全ユーザーの名前とメールが表示されていることを確認
        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee($user->email);
        }
    }

    //　ユーザーの勤怠情報が正しく表示されるかどうか
    public function test_admin_can_view_user_attendance()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 一般ユーザーを作成
        $user = User::factory()->create();

        // 勤怠情報を作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        $response = $this->get('/admin/attendance/staff/' . $user->id);
        $response->assertStatus(200);

        // 勤怠情報が表示されていることを確認
        $response->assertSee($attendance->timestamp->format('H:i'));
    }

    // 「前月」を押下した時に表示月の前月の情報が表示される
    public function test_admin_can_view_previous_month_attendance()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 一般ユーザーを作成
        $user = User::factory()->create();

        // 勤怠情報を作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now()->subMonth(),
        ]);

        $response = $this->get('/admin/attendance/staff/' . $user->id . '?date=' . now()->subMonth()->format('Y-m'));
        $response->assertStatus(200);

        // 勤怠情報が表示されていることを確認
        $response->assertSee($attendance->timestamp->format('H:i'));
    }

    // 「翌月」を押下した時に表示月の前月の情報が表示される
    public function test_admin_can_view_next_month_attendance()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 一般ユーザーを作成
        $user = User::factory()->create();

        // 勤怠情報を作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now()->addMonth(),
        ]);

        $response = $this->get('/admin/attendance/staff/' . $user->id . '?date=' . now()->addMonth()->format('Y-m'));
        $response->assertStatus(200);

        // 勤怠情報が表示されていることを確認
        $response->assertSee($attendance->timestamp->format('H:i'));
    }

    // 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
    public function test_admin_can_view_attendance_detail()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 一般ユーザーを作成
        $user = User::factory()->create();

        // 勤怠情報を作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        $response = $this->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee($attendance->timestamp->format('H:i'));
    }
}
