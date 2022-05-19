<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class RequestPasswordResetTest extends TestCase
{
    private Client $client;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = ClientFactory::new()->asClientCredentials()->create();
        $this->user = UserFactory::new()->create();
    }

    protected function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('requestPasswordResetToken'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/users/password/reset-token', 'requestPasswordResetToken');
    }

    public function testWillReturnValidationErrorsWhenClientCredentialsAreInvalid(): void
    {
        $this->getTestResponse(['email'  => $this->user->email])->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenAttributesAreInvalid(): void
    {
        Passport::actingAsClient($this->client);

        $this->getTestResponse([])->assertUnprocessable()->assertJsonValidationErrors(['email', 'reset_url']);
        $this->getTestResponse([
            'reset_url' => 'https://google.com',
            'email' => 'mymail@yahoo.com'
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'reset_url' => [
                'The reset url attribute must contain :token placeholder',
                'The reset url attribute must contain :email placeholder'
            ]
        ]);
    }

    public function testWillReturnErrorResponseWhenUserDoesNotExists(): void
    {
        Passport::actingAsClient($this->client);

        $this->getTestResponse([
            'email'  => 'non-existentUser@yahoo.com',
            'reset_url' => 'https://url.com?token=:token&email=:email'
        ])
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Could not find user with given email'
            ]);
    }

    public function testSuccessResponse(): void
    {
        Notification::fake();

        Passport::actingAsClient($this->client);

        $this->getTestResponse([
            'email'  => $this->user->email,
            'reset_url' => 'https://url.com/reset?token=:token&email=:email&foo=bar'
        ])->assertOk()->assertExactJson([
            'message' => 'success',
        ]);

        $this->assertDatabaseHas('password_resets', [
            'email' => $this->user->email,
        ]);

        Notification::assertSentTo($this->user, function (ResetPasswordNotification $notification) {
            $this->assertEquals(
                $notification->toMail($this->user)->actionUrl,
                "https://url.com/reset?token=$notification->token&email={$this->user->email}&foo=bar"
            );

            return true;
        });
    }
}
