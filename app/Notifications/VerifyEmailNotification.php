<?php

declare(strict_types=1);

namespace App\Notifications;

use App\ValueObjects\Url;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Stringable;

final class VerifyEmailNotification extends VerifyEmail
{
    public function __construct(private Url $verificationUrl)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function verificationUrl($notifiable)
    {
        $components = $this->parseUrl(
            parse_url(parent::verificationUrl($notifiable))
        );

        return (new Stringable($this->verificationUrl->value))
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
    private function parseUrl(array $parts): array
    {
        $result = [];

        $path = array_filter(explode('/', $parts['path']));

        foreach (explode('&', $parts['query']) as $query) {
            [$key, $value] = explode('=', $query);
            $result[$key] = $value;
        }

        $result['id'] = $path[4];
        $result['hash'] = $path[5];

        return $result;
    }
}
