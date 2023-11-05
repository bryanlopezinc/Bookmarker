<?php

namespace Tests\Feature\User;

use App\Models\SecondaryEmail;
use App\ValueObjects\TwoFACode;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Cache\EmailVerificationCodeRepository as PendingVerifications;

class VerifySecondaryEmailTest extends TestCase
{
    use WithFaker;

    protected function verifySecondaryEmail(array $parameters = []): TestResponse
    {
        return $this->postJson(route('verifySecondaryEmail'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/emails/verify/secondary', 'verifySecondaryEmail');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->verifySecondaryEmail()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenAttributesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->verifySecondaryEmail()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'verification_code']);

        $this->verifySecondaryEmail([
            'email' => 'foo bar',
            'verification_code' => '000',
        ])->assertJsonValidationErrors([
            'email',
            'verification_code' => ['Invalid verification code format']
        ]);

        $this->verifySecondaryEmail([
            'email' => $this->faker->email,
            'verification_code' => '1345.4',
        ])->assertJsonValidationErrors([
            'verification_code' => ['Invalid verification code format']
        ]);
    }

    public function testVerifyEmail(): void
    {
        $email = $this->faker->unique()->email;

        Passport::actingAs($user = UserFactory::new()->create());

        $verificationCode = $this->get2FACode($user->id, $email);

        $this->verifySecondaryEmail([
            'email'             => $email,
            'verification_code' => (string) $verificationCode
        ])->assertOk();

        $this->assertDatabaseHas(SecondaryEmail::class, [
            'email'   => $email,
            'user_id' => $user->id
        ]);
    }

    private function get2FACode(int $userId, string $email): int
    {
        $code = TwoFACode::generate();

        /** @var PendingVerifications */
        $repository = app(PendingVerifications::class);

        $repository->put($userId, $email, $code);

        return $code->value();
    }

    public function testWillReturnNotFoundWhenVerificationCodeHasExpired(): void
    {
        $email = $this->faker->unique()->email;

        Passport::actingAs($user = UserFactory::new()->create());

        $verificationCode = $this->get2FACode($user->id, $email);

        /** @var PendingVerifications */
        $cache = app(PendingVerifications::class);

        $this->travel($cache->getTtl() + 1)->seconds(function () use ($verificationCode, $email) {
            $this->verifySecondaryEmail([
                'email'             => $email,
                'verification_code' => (string) $verificationCode
            ])->assertNotFound()
                ->assertExactJson(['message' => 'VerificationCodeInvalidOrExpired']);
        });
    }

    public function testWillReturnNotFoundWhenVerificationCodeIsNotAssignedToUser(): void
    {
        $users = UserFactory::times(2)->create();

        $email = $this->faker->unique()->email;

        $verificationCode = $this->get2FACode($users[0]->id, $email);

        Passport::actingAs($users[0]);
        $this->verifySecondaryEmail([
            'email'             => $email,
            'verification_code' => TwoFACode::generate()->toString()
        ])->assertNotFound()
            ->assertExactJson($error = ['message' => 'VerificationCodeInvalidOrExpired']);

        $this->verifySecondaryEmail([
            'email'             => $email,
            'verification_code' => strval($verificationCode + 1)
        ])->assertNotFound()
            ->assertExactJson($error);

        Passport::actingAs($users[1]);
        $this->verifySecondaryEmail([
            'email'             => $this->faker->unique()->email,
            'verification_code' => (string) $verificationCode
        ])->assertNotFound()
            ->assertExactJson($error);
    }

    public function testWillReturnNoContentWhenEmailIsAlreadyVerified(): void
    {
        $email = $this->faker->unique()->email;

        $user = UserFactory::new()->create();

        $verificationCode = $this->get2FACode($user->id, $email);

        Passport::actingAs($user);
        $this->verifySecondaryEmail($parameters = [
            'email'             => $email,
            'verification_code' => (string) $verificationCode
        ])->assertOk();

        $this->verifySecondaryEmail($parameters)->assertNoContent();
        $this->verifySecondaryEmail($parameters)->assertNoContent();
    }

    public function testWillReturnNotFoundWhenEmailHasAlreadyBeenVerifiedByAnotherUser(): void
    {
        $email = $this->faker->unique()->email;

        [$firstUser, $secondUser] = UserFactory::times(2)->create();

        $verificationCodes = [$this->get2FACode($firstUser->id, $email), $this->get2FACode($secondUser->id, $email)];

        Passport::actingAs($firstUser);
        $this->verifySecondaryEmail([
            'email'             => $email,
            'verification_code' => (string) $verificationCodes[0]
        ])->assertOk();

        Passport::actingAs($secondUser);
        $this->verifySecondaryEmail([
            'email'             => $email,
            'verification_code' => (string) $verificationCodes[1]
        ])->assertNotFound()
            ->assertExactJson(['message' => 'VerificationCodeInvalidOrExpired']);
    }
}
