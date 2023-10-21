<?php

namespace Tests\Feature\User;

use App\Models\SecondaryEmail;
use App\ValueObjects\TwoFACode;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
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

    protected function addEmailToAccount(array $parameters = []): TestResponse
    {
        return $this->postJson(route('addEmailToAccount'), $parameters);
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

    public function testWillReturnBadRequestWhenVerificationCodeHasExpired(): void
    {
        $email = $this->faker->unique()->email;

        Passport::actingAs($user = UserFactory::new()->create());

        $verificationCode = $this->get2FACode($user->id, $email);

        $this->travel(6)->minutes(function () use ($verificationCode, $email) {
            $this->verifySecondaryEmail([
                'email'             => $email,
                'verification_code' => (string) $verificationCode
            ])->assertStatus(Response::HTTP_BAD_REQUEST)
                ->assertExactJson(['message' => 'VerificationCodeInvalidOrExpired']);
        });
    }

    public function testWillReturnBadRequestWhenVerificationCodeIsNotAssignedToUser(): void
    {
        $users = UserFactory::times(2)->create();

        $email = $this->faker->unique()->email;

        $verificationCode = $this->get2FACode($users[0]->id, $email);

        Passport::actingAs($users[0]);
        $this->verifySecondaryEmail([
            'email'             => $email,
            'verification_code' => TwoFACode::generate()->toString()
        ])->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson($error = ['message' => 'VerificationCodeInvalidOrExpired']);

        $this->verifySecondaryEmail([
            'email'             => $email,
            'verification_code' => strval($verificationCode + 1)
        ])->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson($error);

        Passport::actingAs($users[1]);
        $this->verifySecondaryEmail([
            'email'             => $this->faker->unique()->email,
            'verification_code' => (string) $verificationCode
        ])->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson($error);
    }

    public function testWillReturnForbiddenWhenEmailIsAlreadyVerified(): void
    {
        $email = $this->faker->unique()->email;

        $users = UserFactory::new()->count(2)->create()->all();

        $verificationCodes = [$this->get2FACode($users[0]->id, $email), $this->get2FACode($users[1]->id, $email)];

        Passport::actingAs($users[0]);
        $this->verifySecondaryEmail([
            'email'             => $email,
            'verification_code' => (string) $verificationCodes[0]
        ])->assertOk();

        Passport::actingAs($users[1]);
        $this->verifySecondaryEmail([
            'email'             => $email,
            'verification_code' => (string) $verificationCodes[1]
        ])->assertForbidden()
            ->assertExactJson(['message' => 'EmailAlreadyExists']);
    }
}
