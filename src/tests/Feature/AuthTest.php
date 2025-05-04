<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use App\Models\User;


class AuthTest extends TestCase
{
    use RefreshDatabase;

    //1
    //会員情報登録
    public function test_register_user_successfully_saves_to_database(): void
    {
        $response = $this->post('/register', [
            'name' => '保存テストユーザ',
            'email' => 'save@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/verify-form'); // メール認証画面へのリダイレクト確認

        $this->assertDatabaseHas('users', [
            'name' => '保存テストユーザ',
            'email' => 'save@example.com',
        ]);
    }

    //会員情報登録--名前バリデーション
    public function test_register_name_required_validation(): void
    {
        $response = $this->from('/register') 
                         ->post('/register', [
                             'name' => '', 
                             'email' => 'test@example.com',
                             'password' => 'password123',
                             'password_confirmation' => 'password123',
                         ]);

        $response->assertRedirect('/register'); // バリデーション失敗時のリダイレクト確認

        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    //会員情報登録--メアドバリデーション
    public function test_register_email_required_validation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'テストユーザ',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/register'); // バリデーション失敗時のリダイレクト確認
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    //会員情報登録--パスワードバリデーション
    public function test_register_password_required_validation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'テストユーザ',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }


    //会員情報登録--パスワード7文字以下
    public function test_register_password_min_length_validation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'テストユーザ',
            'email' => 'test@example.com',
            'password' => 'short', // 5文字など
            'password_confirmation' => 'short',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

    //会員情報登録--パスワード不一致
    public function test_register_password_confirmation_mismatch_validation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'テストユーザ',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password321', // 不一致
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    //2
    //スタッフのログイン
    //ログイン--メールアドレス未入力
    public function test_staff_login_email_required_validation(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    //ログイン--パスワード未入力
    public function test_staff_login_password_required_validation(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'staff@example.com',
            'password' => '',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    //ログイン--認証情報が一致しない
    public function test_staff_login_invalid_credentials(): void
    {
        // 正しいユーザーを作成
        User::factory()->create([
            'email' => 'staff@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        // パスワードをわざと間違えてログインを試す
        $response = $this->from('/login')->post('/login', [
            'email' => 'staff@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);
    }
    
    //3
    //管理者のログイン
    //ログイン--メールアドレス未入力
    public function test_admin_login_email_required_validation(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    //ログイン--パスワード未入力
    public function test_admin_login_password_required_validation(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'staff@example.com',
            'password' => '',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    //ログイン--認証情報が一致しない
    public function test_admin_login_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません。',
        ]);
    }

}
