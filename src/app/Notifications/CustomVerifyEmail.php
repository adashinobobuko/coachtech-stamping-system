<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;

class CustomVerifyEmail extends Notification
{
    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // 署名付きURLを生成（デフォルトのFortifyと同じ）
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify', // Fortifyで自動定義されているルート名
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('メール認証')
            ->view('email.verify', [ 
                'user' => $notifiable,
                'verificationUrl' => $url,
            ]);
    }
}
