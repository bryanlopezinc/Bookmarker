<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
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

    public function testVerificationUrlMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['verification_url' => 'foo_bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'verification_url' => ['The verification url must be a valid URL.']
            ]);

        $this->getTestResponse(['verification_url' => $this->faker->url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :id placeholder",
                    "The verification url attribute must contain :hash placeholder",
                    "The verification url attribute must contain :signature placeholder",
                    "The verification url attribute must contain :expires placeholder",
                ]
            ]);

        $this->getTestResponse(['verification_url' => $this->faker->url . '?id=:id'])
            ->assertUnprocessable()
            ->assertJsonCount(3, 'errors.verification_url')
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :hash placeholder",
                    "The verification url attribute must contain :signature placeholder",
                    "The verification url attribute must contain :expires placeholder",
                ]
            ]);

        $this->getTestResponse(['verification_url' => $this->faker->url . '?hash=:hash'])
            ->assertUnprocessable()
            ->assertJsonCount(3, 'errors.verification_url')
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :id placeholder",
                    "The verification url attribute must contain :signature placeholder",
                    "The verification url attribute must contain :expires placeholder",
                ]
            ]);

        $this->getTestResponse(['verification_url' => $this->faker->url . '?signature=:signature'])
            ->assertUnprocessable()
            ->assertJsonCount(3, 'errors.verification_url')
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :id placeholder",
                    "The verification url attribute must contain :hash placeholder",
                    "The verification url attribute must contain :expires placeholder",
                ]
            ]);

        $this->getTestResponse(['verification_url' => $this->faker->url . '?expires=:expires'])
            ->assertUnprocessable()
            ->assertJsonCount(3, 'errors.verification_url')
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :id placeholder",
                    "The verification url attribute must contain :hash placeholder",
                    "The verification url attribute must contain :signature placeholder",
                ]
            ]);
    }

    public function testWillResendLink(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        static::$user = $user;

        Notification::fake();

        $this->getTestResponse(['verification_url' => $this->verificationUrl()])->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
            static::$verificationUrl =  $notification->toMail($user)->actionUrl;

            return true;
        });
    }

    private function verificationUrl(): string
    {
        return $this->faker->url . '?' .  http_build_query([
            'id' => ':id',
            'hash' => ':hash',
            'signature' => ':signature',
            'expires' => ':expires'
        ]);
    }

    /**
     * @depends testWillResendLink
     */
    public function testCanVerifyEmailWithParameters(): void
    {
        Passport::actingAs(static::$user);

        $components = $this->parseQuery(static::$verificationUrl);

        $uri = route('verification.verify', [
            $components['id'],
            $components['hash'],
            'signature' => $components['signature'],
            'expires' => $components['expires']
        ]);

        $this->getJson($uri)->assertOk();

        $this->assertTrue(User::whereKey($components['id'])->sole()->email_verified_at->isToday());
    }

    private function parseQuery(string $url): array
    {
        $parts = parse_url($url);

        $result = [];

        foreach (explode('&', $parts['query']) as $query) {
            [$key, $value] = explode('=', $query);
            $result[$key] = $value;
        }

        return $result;
    }

    public function testCanOnlyMake_6_RequestsPerMinute(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        for ($i = 0; $i < 6; $i++) {
            $this->getTestResponse(['verification_url' => $this->verificationUrl()])->assertOk();
        }

        $this->getTestResponse(['verification_url' => $this->verificationUrl()])->assertStatus(429);
    }
}
