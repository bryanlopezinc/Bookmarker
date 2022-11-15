<?php

namespace Tests\Feature\User;

use App\ValueObjects\TwoFACode;
use App\Mail\TwoFACodeMail;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Passport;

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

    public function testWillReturnErrorResponseWhenUserDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->twoFARequestResponse([
            'username'  => UserFactory::randomUsername(),
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function testWillReturnErrorResponseWhenUserPasswordDoesNotMatch(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->twoFARequestResponse([
            'username'  => UserFactory::new()->create()->username,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function testSuccessResponse(): void
    {
        $verificationCode = TwoFACode::generate()->code();
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
            $this->assertSame($verificationCode, $mail->get2FACode()->code());
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

    public function testWillReturnErrorResponseWhenRequestIsSentMoreThanOnceInAMinute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $username = UserFactory::new()->create()->username;

        $this->twoFARequestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertOk();

        $this->twoFARequestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $this->twoFARequestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testCanRequestNewTokenAfterOneMinute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $username = UserFactory::new()->create()->username;

        $this->twoFARequestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertOk();

        $this->twoFARequestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $this->travel(62)->seconds();

        $this->twoFARequestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertOk();
    }
}
