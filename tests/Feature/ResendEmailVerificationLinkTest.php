<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\ValueObjects\Url;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ResendEmailVerificationLinkTest extends TestCase
{
    use WithFaker;

    private static string $verificationUrl;
    private static User $user;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('verification.resend'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/email/verify/resend', 'verification.resend');
    }

    public function testWillResendLink(): void
    {
        config(['settings.EMAIL_VERIFICATION_URL' => $this->faker->url . '?id=:id&hash=:hash&signature=:signature&expires=:expires']);

        Passport::actingAs($user = UserFactory::new()->create());

        static::$user = $user;

        Notification::fake();

        $this->getTestResponse()->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
            static::$verificationUrl =  $notification->toMail($user)->actionUrl;

            return true;
        });
    }

    /**
     * @depends testWillResendLink
     */
    public function testCanVerifyEmailWithParameters(): void
    {
        Passport::actingAs(static::$user);

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

    public function testCanOnlyMake_6_RequestsPerMinute(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        for ($i = 0; $i < 6; $i++) {
            $this->getTestResponse()->assertOk();
        }

        $this->getTestResponse()->assertStatus(429);
    }
}
