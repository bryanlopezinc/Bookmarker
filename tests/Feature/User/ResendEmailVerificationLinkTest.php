<?php

namespace Tests\Feature\User;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\ValueObjects\Url;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ResendEmailVerificationLinkTest extends TestCase
{
    use WithFaker;

    private static string $verificationUrl;

    protected function resendVerificationLinkResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('verification.resend'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/email/verify/resend', 'verification.resend');
    }

    public function testUnauthorizedClientCannotAccessRoute(): void
    {
        $this->resendVerificationLinkResponse()->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenEmailDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->resendVerificationLinkResponse(['email' => UserFactory::new()->make()->email])->assertNotFound();
    }

    public function testWhenEmailIsAlreadyVerified(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->resendVerificationLinkResponse([
            'email' => UserFactory::new()->create()->email
        ])->assertOk()
            ->assertJson(['message' => 'EmailAlreadyVerified']);
    }

    public function testResendLink(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        config(['settings.EMAIL_VERIFICATION_URL' => $this->faker->url . '?id=:id&hash=:hash&signature=:signature&expires=:expires']);

        $user = UserFactory::new()->unverified()->create();

        Notification::fake();

        $this->resendVerificationLinkResponse(['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
            static::$verificationUrl =  $notification->toMail($user)->actionUrl;

            return true;
        });
    }

    /**
     * @depends testResendLink
     */
    public function testCanVerifyEmailWithParameters(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $components = (new Url(static::$verificationUrl))->parseQuery();

        $uri = route('verification.verify', [
            $components['id'],
            $components['hash'],
            'signature' => $components['signature'],
            'expires' => $components['expires']
        ]);

        $this->getJson($uri)->assertOk();

        $this->assertTrue(User::whereKey($components['id'])->sole()->email_verified_at->isToday());
    }

    public function testWillThrottleRequestWhenUserMakesMoreThan_6_RequestsPerMinute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $user = UserFactory::new()->unverified()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->resendVerificationLinkResponse([
                'email' => $user->email
            ])->assertOk();
        }

        $this->resendVerificationLinkResponse(['email' => $user->email])->assertStatus(429);
    }
}
