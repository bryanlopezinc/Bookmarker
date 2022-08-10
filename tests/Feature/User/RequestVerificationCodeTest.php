<?php

namespace Tests\Feature\User;

use App\ValueObjects\VerificationCode;
use App\Contracts\VerificationCodeGeneratorInterface;
use App\Mail\VerificationCodeMail;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Passport;

class RequestVerificationCodeTest extends TestCase
{
    private function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('requestVerificationCode'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/users/request-verification-code', 'requestVerificationCode');
    }

    public function testUnAuthorizedClientCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenClientCredentialsAreInvalid(): void
    {
        $this->getTestResponse([])->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function testWillReturnErrorResponseWhenUserDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse([
            'username'  => UserFactory::randomUsername(),
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function testWillReturnErrorResponseWhenUserPasswordDoesNotMatch(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse([
            'username'  => UserFactory::new()->create()->username,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function testSuccessResponse(): void
    {
        Mail::fake();
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $mock = $this->getMockBuilder(VerificationCodeGeneratorInterface::class)->getMock();
        $mock->expects($this->once())->method('generate')->willReturn(new VerificationCode($verificationCode = 12345));
        $this->swap(VerificationCodeGeneratorInterface::class, $mock);

        $user = UserFactory::new()->create();

        $this->getTestResponse([
            'username'  => $user->username,
            'password' => 'password',
        ])->assertOk();

        Mail::assertSent(function (VerificationCodeMail $mail) use ($user, $verificationCode) {
            $this->assertSame($user->email, $mail->to[0]['address']);
            $this->assertSame($verificationCode, $mail->getVerificationCode()->code());
            return true;
        });
    }

    public function testCanRequestCodeWithValidEmail(): void
    {
        Mail::fake();

        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $user = UserFactory::new()->create();

        $this->getTestResponse([
            'username'  => $user->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function testWillReturnErrorResponseWhenRequestIsSentMoreThanOnceInAMinute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $username = UserFactory::new()->create()->username;

        $this->getTestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertOk();

        $this->getTestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $this->getTestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testCanRequestNewTokenAfterOneMinute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $username = UserFactory::new()->create()->username;

        $this->getTestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertOk();

        $this->getTestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $this->travel(62)->seconds();

        $this->getTestResponse([
            'username'  => $username,
            'password' => 'password',
        ])->assertOk();
    }
}
