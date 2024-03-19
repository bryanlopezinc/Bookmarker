<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Utils\UrlPlaceholders;
use App\ValueObjects\Url;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Stringable;
use Exception;

final class VerifyEmailNotification extends VerifyEmail
{
    /**
     * {@inheritdoc}
     */
    protected function verificationUrl($notifiable)
    {
        $components = $this->parseUrl(parent::verificationUrl($notifiable));

        return (new Stringable($this->getVerifyEmailUrl()))
            ->replace(':expires', $components['expires'])
            ->replace(':signature', $components['signature'])
            ->replace(':hash', $components['hash'])
            ->replace(':id', $components['id'])
            ->toString();
    }

    private function getVerifyEmailUrl(): string
    {
        $url = config('settings.EMAIL_VERIFICATION_URL');

        //Validation.
        new Url($url);

        // @codeCoverageIgnoreStart
        if ($missing = UrlPlaceholders::missing($url, [':id', ':hash', ':signature', ':expires'])) {
            $missing = implode(',', $missing);

            throw new Exception("The verification url  must contain the [{$missing}] placeholder/s");
        }
        // @codeCoverageIgnoreEnd

        return $url;
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
