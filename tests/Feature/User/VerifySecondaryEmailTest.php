<?php

namespace Tests\Feature\User;

use App\Mail\TwoFACodeMail;
use App\Models\SecondaryEmail;
use App\ValueObjects\TwoFACode;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

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

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->verifySecondaryEmail()->assertUnauthorized();
    }

    public function testAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->verifySecondaryEmail()->assertJsonValidationErrors(['email', 'verification_code']);
    }

    public function testAttributesMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

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

        $verificationCode = $this->get2FACode(function () use ($email) {
            $this->addEmailToAccount(['email' => $email])->assertOk();
        });

        $this->verifySecondaryEmail([
            'email' => $email,
            'verification_code' => (string) $verificationCode
        ])->assertOk();

        $this->assertDatabaseHas(SecondaryEmail::class, [
            'email' => $email,
            'user_id' => $user->id
        ]);
    }

    public function testCannotUseVerificationCodeAfter_5_minutes(): void
    {
        $email = $this->faker->unique()->email;

        Passport::actingAs(UserFactory::new()->create());

        $verificationCode = $this->get2FACode(function () use ($email) {
            $this->addEmailToAccount(['email' => $email])->assertOk();
        });

        $this->travel(6)->minutes(function () use ($verificationCode, $email) {
            $this->verifySecondaryEmail([
                'email' => $email,
                'verification_code' => (string) $verificationCode
            ])->assertStatus(Response::HTTP_BAD_REQUEST)
                ->assertExactJson(['message' => 'Verification code invalid or expired']);
        });
    }

    public function testMustFirstRequestVerificationCode(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->verifySecondaryEmail([
            'email' => $this->faker->unique()->email,
            'verification_code' => TwoFACode::generate()->toString()
        ])->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson(['message' => 'Verification code invalid or expired']);
    }

    public function testVerificationCodeMustBeSameCodeSentToEmail(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $email = $this->faker->unique()->email;

        $verificationCode = $this->get2FACode(function () use ($email) {
            $this->addEmailToAccount(['email' => $email])->assertOk();
        });

        $invalidVerificationCode = $verificationCode - 1;

        $this->verifySecondaryEmail([
            'email' => $email,
            'verification_code' =>(string) $invalidVerificationCode
        ])->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson(['message' => 'Verification code invalid or expired']);
    }

    public function testVerificationCodeMustMatchCodeAssignedToUserAccount(): void
    {
        $email = $this->faker->unique()->email;
        [$john, $alex] = UserFactory::new()->count(2)->create()->all();

        Passport::actingAs($john);
        $verificationCodeSentOnBehalfOfJohn = $this->get2FACode(function () use ($email) {
            $this->addEmailToAccount(['email' => $email])->assertOk();
        });

        //alex adds same email (alex and john both have access to same email).
        Passport::actingAs($alex);
        $this->addEmailToAccount(['email' => $email])->assertOk();

        //johns code arrives first, alex wants to beat john to it by using verification code generated for john.
        $this->verifySecondaryEmail([
            'email' => $email,
            'verification_code' => (string) $verificationCodeSentOnBehalfOfJohn
        ])->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson(['message' => 'Verification code invalid or expired']);
    }

    public function testCannotVerifyAlreadyVerifiedEmailWithValidCode(): void
    {
        $email = $this->faker->unique()->email;

        [$brian, $stewie] = UserFactory::new()->count(2)->create()->all();

        //brian adds email
        Passport::actingAs($brian);
        $verificationCodeSentOnBehalfOfBrian = $this->get2FACode(function () use ($email) {
            $this->addEmailToAccount(['email' => $email])->assertOk();
        });

        //stewie adds same email (stewie and brian both have access to same email).
        Passport::actingAs($stewie);
        $verificationCodeSentOnBehalfOfStewie = $this->get2FACode(function () use ($email) {
            $this->addEmailToAccount(['email' => $email])->assertOk();
        });

        //stewie is too busy with rupert , brian verifies first
        Passport::actingAs($brian);
        $this->verifySecondaryEmail([
            'email' => $email,
            'verification_code' => (string) $verificationCodeSentOnBehalfOfBrian
        ])->assertOk();

        //stewie hates rupert now.
        Passport::actingAs($stewie);
        $this->verifySecondaryEmail([
            'email' => $email,
            'verification_code' => (string) $verificationCodeSentOnBehalfOfStewie
        ])->assertForbidden()
            ->assertExactJson(['message' => 'Email already exists']);
    }

    private function get2FACode(\Closure $callback): int
    {
        $verificationCode = 0;
        Mail::fake();
        $callback();

        Mail::assertQueued(function (TwoFACodeMail $mail) use (&$verificationCode) {
            $verificationCode = $mail->get2FACode()->code();
            return true;
        });

        return $verificationCode;
    }

    public function testVerificationCodeMustMatchCodeAssignedEmail(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $emailAddedToAccount = $this->faker->unique()->email;
        $anotherEmail = $this->faker->unique()->email;

        $verificationCode = $this->get2FACode(function () use ($emailAddedToAccount) {
            $this->addEmailToAccount(['email' => $emailAddedToAccount])->assertOk();
        });

        $this->verifySecondaryEmail([
            'email' => $anotherEmail,
            'verification_code' => (string) $verificationCode
        ])->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson(['message' => 'Verification code invalid or expired']);
    }

    public function testCanAddNewEmailAfterVerification(): void
    {
        $email = $this->faker->unique()->email;

        Passport::actingAs(UserFactory::new()->create());

        $verificationCode = $this->get2FACode(function () use ($email) {
            $this->addEmailToAccount(['email' => $email])->assertOk();
        });

        $this->verifySecondaryEmail([
            'email' => $email,
            'verification_code' => (string) $verificationCode
        ])->assertOk();

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();
    }
}
