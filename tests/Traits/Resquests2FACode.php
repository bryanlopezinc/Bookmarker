<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Utils\TwoFACodeGenerator;
use App\Contracts\TwoFACodeGeneratorInterface;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Laravel\Passport\TokenRepository;

trait Resquests2FACode
{
    private function get2FACode(string $username, string $password): int
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $code = (new TwoFACodeGenerator)->generate();

        $mock = $this->getMockBuilder(TwoFACodeGeneratorInterface::class)->getMock();
        $mock->expects($this->once())->method('generate')->willReturn($code);
        $this->swap(TwoFACodeGeneratorInterface::class, $mock);

        $this->postJson(route('requestVerificationCode'), [
            'username' => $username,
            'password' => $password
        ])->assertSuccessful();

        app()->forgetInstance(TokenRepository::class);
        app()->forgetInstance(\League\OAuth2\Server\ResourceServer::class);

        return $code->code();
    }
}