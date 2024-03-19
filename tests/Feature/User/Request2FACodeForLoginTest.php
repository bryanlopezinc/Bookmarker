<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\ValueObjects\TwoFACode;
use App\Mail\TwoFACodeMail;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;

class Request2FACodeForLoginTest extends TestCase
{
    private function twoFARequestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('requestVerificationCode'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/request-verification-code', 'requestVerificationCode');
    }

    public function testUnAuthorizedClientCannotAccessRoute(): void
    {
        $this->twoFARequestResponse()->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenClientCredentialsAreInvalid(): void
    {
        $this->twoFARequestResponse([])->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->twoFARequestResponse([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function testWillReturnUnauthorizedResponseWhenUserDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->twoFARequestResponse([
            'username'  => UserFactory::randomUsername(),
            'password' => 'password',
        ])->assertUnauthorized()
            ->assertExactJson(['message' => 'InvalidCredentials']);
    }

    public function testWillReturnUnauthorizedResponseWhenUserPasswordDoesNotMatch(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->twoFARequestResponse([
            'username'  => UserFactory::new()->create()->username,
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertExactJson(['message' => 'InvalidCredentials']);
    }

    public function testRequestCodeWithUserName(): void
    {
        $verificationCode = TwoFACode::generate()->value();
        $user = UserFactory::new()->create();

        TwoFACode::useGenerator(fn () => $verificationCode);
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());
        Mail::fake();

        $this->twoFARequestResponse([
            'username'  => $user->username,
            'password' => 'password',
        ])->assertOk();

        Mail::assertQueued(function (TwoFACodeMail $mail) use ($user, $verificationCode) {
            $this->assertSame($user->email, $mail->to[0]['address']);
            $this->assertSame($verificationCode, $mail->get2FACode()->value());
            return true;
        });

        TwoFACode::useGenerator();
    }

    public function testCanRequestCodeWithValidEmail(): void
    {
        Mail::fake();

        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $user = UserFactory::new()->create();

        $this->twoFARequestResponse([
            'username'  => $user->email,
            'password' => 'password',
        ])->assertOk();
    }

    #[Test]
    public function canOnlyRequestCodeOncePerMinute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $username = UserFactory::new()->create()->username;

        $this->twoFARequestResponse($query = [
            'username'  => $username,
            'password' => 'password',
        ])->assertOk();

        $this->twoFARequestResponse($query)
            ->assertTooManyRequests()
            ->assertHeader('request-2FA-after')
            ->tap(function (TestResponse $response) {
                $this->assertLessThanOrEqual($response->baseResponse->headers->get('request-2FA-after'), 59);
            });

        $this->travel(61)->seconds(function () use ($query) {
            $this->twoFARequestResponse($query)->assertOk();
        });
    }
}
