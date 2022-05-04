<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Str;

final class ResetPasswordNotification extends ResetPassword
{
    private string $resetPasswordUrl;

    public function __construct(string $token, string $resetPasswordUrl)
    {
        parent::__construct($token);

        $this->resetPasswordUrl = $resetPasswordUrl;
    }

    protected function resetUrl($notifiable)
    {
        return Str::of($this->resetPasswordUrl)
            ->replace(':token', $this->token)
            ->replace(':email', $notifiable->getEmailForPasswordReset())
            ->toString();
    }
}
