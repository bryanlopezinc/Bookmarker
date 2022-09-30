<?php

declare(strict_types=1);

namespace App\Notifications;

use App\ValueObjects\Url;
use App\VerifyEmailUrl;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Stringable;

final class VerifyEmailNotification extends VerifyEmail
{
    /**
     * {@inheritdoc}
     */
    protected function verificationUrl($notifiable)
    {
        $components = $this->parseUrl(parent::verificationUrl($notifiable));

        return (new Stringable((string)new VerifyEmailUrl))
            ->replace(':expires', $components['expires'])
            ->replace(':signature', $components['signature'])
            ->replace(':hash', $components['hash'])
            ->replace(':id', $components['id'])
            ->toString();
    }

    /**
     * Parse the query string  and path variables into an associative array.
     *
     * @return array<string,string>
     */
    private function parseUrl(string $url): array
    {
        $url = new Url($url);
        $path = array_filter(explode('/', $url->getPath()));

        $queryParameters = $url->parseQuery();

        $queryParameters['id'] = $path[4];
        $queryParameters['hash'] = $path[5];

        return $queryParameters;
    }
}
