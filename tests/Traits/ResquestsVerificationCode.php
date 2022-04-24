<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\TwoFA\RandomNumberCodeGenerator;
use App\TwoFA\VerificationCodeGeneratorInterface;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Laravel\Passport\TokenRepository;

trait ResquestsVerificationCode
{
    private function getVerificationCode(string $username, string $password): int
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $code = (new RandomNumberCodeGenerator)->generate();

        $mock = $this->getMockBuilder(VerificationCodeGeneratorInterface::class)->getMock();
        $mock->expects($this->once())->method('generate')->willReturn($code);
        $this->swap(VerificationCodeGeneratorInterface::class, $mock);

        $this->postJson(route('requestVerificationCode'), [
            'username' => $username,
            'password' => $password
        ])->assertSuccessful();

        app()->forgetInstance(TokenRepository::class);
        app()->forgetInstance(\League\OAuth2\Server\ResourceServer::class);

        return $code->value;
    }
}