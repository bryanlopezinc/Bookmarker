<?php

namespace Tests\Feature\User;

use App\Mail\TwoFACodeMail;
use App\Models\SecondaryEmail;
use Database\Factories\EmailFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Cache\EmailVerificationCodeRepository as PendingVerifications;

class AddEmailTest extends TestCase
{
    use WithFaker;

    protected function addEmailToAccount(array $parameters = []): TestResponse
    {
        return $this->postJson(route('addEmailToAccount'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/emails/add', 'addEmailToAccount');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->addEmailToAccount()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenAttributesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->withRequestId();

        $this->addEmailToAccount()->assertUnprocessable()->assertJsonValidationErrorFor('email');
        $this->addEmailToAccount(['email' => 'foo bar'])->assertJsonValidationErrorFor('email');
    }

    public function testAddEmail(): void
    {
        $this->withRequestId();

        Mail::fake();

        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();

        Mail::assertQueued(TwoFACodeMail::class);
    }

    public function testCanAddAnotherEmailAfter_5_minutes_WithoutVerifyingFirstEmail(): void
    {
        $this->withRequestId();

        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();

        /** @var PendingVerifications */
        $cache = app(PendingVerifications::class);

        $this->travel($cache->getTtl() + 1)->seconds(function () {
            $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();
        });
    }

    public function testWillReturnForbiddenWhenSecondaryEmailsIsMoreThan_3(): void
    {
        $this->withRequestId();

        Passport::actingAs($user = UserFactory::new()->create());

        EmailFactory::times(3)->for($user)->create();

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])
            ->assertForbidden()
            ->assertExactJson(['message' => 'MaxEmailsLimitReached']);
    }

    public function testWillReturnBadRequestWhenUserHasEmailAwaitingVerification(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->withRequestId()->addEmailToAccount(['email' => $this->faker->unique()->email]);

        $this->withRequestId()
            ->addEmailToAccount(['email' => $this->faker->unique()->email])
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson(['message'    => 'AwaitingVerification']);
    }

    public function testWillReturnUnprocessableWhenEmailAlreadyExists(): void
    {
        $this->withRequestId();

        [$user, $otherUser] = UserFactory::times(2)->create();

        SecondaryEmail::create([
            'user_id'     => $user->id,
            'email'       => $userSecondaryEmail = $this->faker->unique()->email,
            'verified_at' => now()
        ]);

        SecondaryEmail::create([
            'user_id'     => $otherUser->id,
            'email'       => $otherUSecondaryEmail = $this->faker->unique()->email,
            'verified_at' => now()
        ]);

        Passport::actingAs($user);
        $this->addEmailToAccount(['email' => $userSecondaryEmail])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorMessage = ['email' => 'The email has already been taken.']);

        $this->addEmailToAccount(['email' => $user->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorMessage);

        $this->addEmailToAccount(['email' => $otherUser->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorMessage);

        $this->addEmailToAccount(['email' => $otherUSecondaryEmail])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorMessage);
    }
}
