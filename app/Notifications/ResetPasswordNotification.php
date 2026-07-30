<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Reset Your NEP System Password')
            ->view('emails.reset-password', [
                'resetUrl'  => $resetUrl,
                'userName'  => $notifiable->name,
                'expiresIn' => config('auth.passwords.users.expire', 60),
            ]);
    }
}
