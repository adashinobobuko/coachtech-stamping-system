<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Admin;
use App\Models\AttendanceApplication;

class AdminGetInfomationTest extends TestCase
{
    use RefreshDatabase;

    // 12
    // その日になされた全ユーザーの勤怠情報が正確に確認できる
    public function test_admin_can_view_all_attendance()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // ユーザーを2人作成
        $user1 = User::factory()->create(['name' => 'ユーザーA']);
        $user2 = User::factory()->create(['name' => 'ユーザーB']);

        // 各ユーザーに勤怠を登録
        Attendance::factory()->create([
            'user_id' => $user1->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);
        Attendance::factory()->create([
            'user_id' => $user2->id,
            'type' => 'clock_in',
            'timestamp' => now(),
        ]);

        // 管理者が勤怠画面にアクセス
        $response = $this->get('/admin/attendance/list');
        $response->assertStatus(200);

        // 両方のユーザー名が画面に表示されているか確認
        $response->assertSee('ユーザーA');
        $response->assertSee('ユーザーB');
    }

    // 管理者の勤怠一覧に遷移した際に現在の日付が表示される
    public function test_admin_attendance_list_displays_current_date()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 管理者が勤怠画面にアクセス
        $response = $this->get('/admin/attendance/list');
        $response->assertStatus(200);

        // 現在の日付が表示されているか確認
        $response->assertSee(now()->format('Y/m/d'));
    }

    // 「前日」を押下した時に前の日の勤怠情報が表示される
    public function test_admin_attendance_list_displays_previous_day()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // ユーザーを作成
        $user = User::factory()->create(['name' => 'ユーザーA']);

        // 前日の勤怠を登録
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now()->subDay(),
        ]);

        // 管理者が勤怠画面にアクセス
        $response = $this->get('/admin/attendance/list?date=' . now()->subDay()->format('Y-m-d'));
        $response->assertStatus(200);

        // ユーザー名が画面に表示されているか確認
        $response->assertSee('ユーザーA');
    }

    // 「翌日」を押下した時に翌の日の勤怠情報が表示される
    public function test_admin_attendance_list_displays_next_day()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // ユーザーを作成
        $user = User::factory()->create(['name' => 'ユーザーA']);

        // 翌日の勤怠を登録
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => 'clock_in',
            'timestamp' => now()->addDay(),
        ]);

        // 管理者が勤怠画面にアクセス
        $response = $this->get('/admin/attendance/list?date=' . now()->addDay()->format('Y-m-d'));
        $response->assertStatus(200);

        // ユーザー名が画面に表示されているか確認
        $response->assertSee('ユーザーA');
    }
}
