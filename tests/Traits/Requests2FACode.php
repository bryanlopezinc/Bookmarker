<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\ValueObjects\TwoFACode;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Laravel\Passport\TokenRepository;

trait Requests2FACode
{
    private function get2FACode(string $username, string $password): int
    {
        $code = TwoFACode::generate()->value();
        TwoFACode::useGenerator(fn () => $code);

        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());
        $this->postJson(route('requestVerificationCode'), [
            'username' => $username,
            'password' => $password
        ])->assertSuccessful();

        app()->forgetInstance(TokenRepository::class);
        app()->forgetInstance(\League\OAuth2\Server\ResourceServer::class);

        TwoFACode::useGenerator();

        return $code;
    }
}
