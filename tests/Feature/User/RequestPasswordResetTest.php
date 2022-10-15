<?php

namespace Tests\Feature\User;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Passport;

class RequestPasswordResetTest extends TestCase
{
    protected function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('requestPasswordResetToken'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/password/reset-token', 'requestPasswordResetToken');
    }

    public function testUnauthorizedClientCannotAccessRoute(): void
    {
        $this->getTestResponse(['email'  => UserFactory::new()->create()->email])->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenAttributesAreInvalid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asClientCredentials()->create());

        $this->getTestResponse([])->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->getTestResponse(['email' => 'my mail@yahoo.com'])->assertUnprocessable();
    }

    public function testWillReturnErrorResponseWhenUserDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asClientCredentials()->create());

        $this->getTestResponse(['email'  => 'non-existentUser@yahoo.com'])
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'User not found'
            ]);
    }

    public function testSuccessResponse(): void
    {
        config(['settings.RESET_PASSWORD_URL' => 'https://url.com/reset?token=:token&email=:email&foo=bar']);

        Notification::fake();

        Passport::actingAsClient(ClientFactory::new()->asClientCredentials()->create());

        $user = UserFactory::new()->create();

        $this->getTestResponse(['email'  => $user->email,])
            ->assertOk()
            ->assertExactJson(['message' => 'success']);

        $this->assertDatabaseHas('password_resets', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, function (ResetPasswordNotification $notification) use ($user) {
            $this->assertEquals(
                $notification->toMail($user)->actionUrl,
                "https://url.com/reset?token=$notification->token&email={$user->email}&foo=bar"
            );

            return true;
        });
    }
}
