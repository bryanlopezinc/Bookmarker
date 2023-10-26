<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Utils\UrlPlaceholders;
use App\ValueObjects\Url;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    protected function resetUrl($notifiable)
    {
        return Str::of($this->getResetUrl())
            ->replace(':token', $this->token)
            ->replace(':email', $notifiable->getEmailForPasswordReset())
            ->toString();
    }

    private function getResetUrl(): string
    {
        $url = config('settings.RESET_PASSWORD_URL');

        //Validation.
        new Url($url);

        if ($missing = UrlPlaceholders::missing($url, [':email', ':token'])) {
            $missing = implode(',', $missing);

            throw new \Exception("The verification url  must contain the [$missing] placeholder/s");
        }

        return $url;
    }
}
