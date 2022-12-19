<?php

declare(strict_types=1);

namespace App\Notifications;

use App\ResetPasswordUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    protected function resetUrl($notifiable)
    {
        return Str::of((string) new ResetPasswordUrl())
            ->replace(':token', $this->token)
            ->replace(':email', $notifiable->getEmailForPasswordReset())
            ->toString();
    }
}
