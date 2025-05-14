<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmail;


class MailVerifyTest extends TestCase
{
    use RefreshDatabase;

    // 16
    // 会員登録後、認証メールが送信されるかどうか
    public function test_verification_email_is_sent_after_registration()
    {
        Mail::fake();
    
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    
        $response->assertRedirect('/verify-form');
        $response->assertSessionHas('message', '認証メールを送信しました！メールを確認してください。');
    }
    
    // メール認証誘導画面で「認証はこちらから」ボタンを押下するとメール認証サイトに遷移するかどうか
    public function test_verification_email_link_redirects_to_verification_page()
    {
        $user = User::factory()->create([
            'email_verification_token' => \Str::random(64),
        ]);
    
        // セッションに user_id を明示的にセット
        session(['user_id' => $user->id]);
    
        $response = $this->actingAs($user)->get('/verify-form');
    
        $response->assertStatus(200);
        $response->assertSee('認証はこちらから');
        $response->assertSee(route('verify.email', ['token' => $user->email_verification_token]));
    }

    // メール認証サイトのメール認証を完了すると、打刻ページに遷移するかどうか
    public function test_verification_email_link_redirects_to_attendance_page()
    {
        $user = User::factory()->create([
            'email_verification_token' => \Str::random(64),
        ]);
    
        $response = $this->get(route('verify.email', ['token' => $user->email_verification_token]));
    
        $response->assertStatus(302);
        $response->assertRedirect('/attendance');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email_verified_at' => now(),
        ]);
    }
}
