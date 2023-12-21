<?php

namespace Tests\Feature\User;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;

class RequestPasswordResetTest extends TestCase
{
    use WithFaker;

    protected function requestPasswordResetResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('requestPasswordResetToken'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/password/reset-token', 'requestPasswordResetToken');
    }

    public function testUnauthorizedClientCannotAccessRoute(): void
    {
        $this->requestPasswordResetResponse(['email'  => UserFactory::new()->create()->email])->assertUnauthorized();
    }

    public function testAttributesMustBeValidInvalid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asClientCredentials()->create());

        $this->withRequestId();

        $this->requestPasswordResetResponse([])->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->requestPasswordResetResponse(['email' => 'my mail@yahoo.com'])->assertUnprocessable();
    }

    public function testWillReturnNotFoundWhenDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asClientCredentials()->create());

        $this->withRequestId()
            ->requestPasswordResetResponse(['email'  => $this->faker->email])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }

    public function testSendPasswordResetLink(): void
    {
        config(['settings.RESET_PASSWORD_URL' => 'https://url.com/reset/:token?email=:email&foo=bar&t=b']);

        Notification::fake();

        Passport::actingAsClient(ClientFactory::new()->asClientCredentials()->create());

        $user = UserFactory::new()->create();

        $this->withRequestId()
            ->requestPasswordResetResponse(['email'  => $user->email,])
            ->assertOk()
            ->assertExactJson(['message' => 'success']);

        $this->assertDatabaseHas('password_resets', ['email' => $user->email]);

        Notification::assertSentTo($user, function (ResetPasswordNotification $notification) use ($user) {
            $this->assertEquals(
                $notification->toMail($user)->actionUrl,
                "https://url.com/reset/{$notification->token}?email={$user->email}&foo=bar&t=b"
            );

            return true;
        });
    }

    #[Test]
    public function canOnlySendOneResetLinkPerMinute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asClientCredentials()->create());

        $user = UserFactory::new()->create();

        $this->withRequestId()
            ->requestPasswordResetResponse(['email' => $user->email,])
            ->assertOk()
            ->assertExactJson(['message' => 'success']);

        $this->withRequestId()
            ->requestPasswordResetResponse(['email' => $user->email,])
            ->assertTooManyRequests();

        $this->travel(61)->seconds(function () use ($user) {
            $this->withRequestId()->requestPasswordResetResponse(['email' => $user->email,])->assertOk();
        });
    }
}
