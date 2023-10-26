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

        $this->addEmailToAccount()->assertUnprocessable()->assertJsonValidationErrorFor('email');
        $this->addEmailToAccount(['email' => 'foo bar'])->assertJsonValidationErrorFor('email');
    }

    public function testAddEmail(): void
    {
        Mail::fake();

        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();

        Mail::assertQueued(TwoFACodeMail::class);
    }

    public function testCanAddAnotherEmailAfter_5_minutes_WithoutVerifyingFirstEmail(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();

        $this->travel(6)->minutes(function () {
            $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();
        });
    }

    public function testWillReturnForbiddenWhenSecondaryEmailsIsMoreThan_3(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        EmailFactory::times(3)->for($user)->create();
  
        $this->addEmailToAccount(['email' => $this->faker->unique()->email])
            ->assertForbidden()
            ->assertExactJson(['message' => 'MaxEmailsLimitReached']);
    }

    public function testWillReturnBadRequestWhenUserHasEmailAwaitingVerification(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $this->faker->unique()->email]);

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson(['message'    => 'AwaitingVerification']);
    }

    public function testWillReturnUnprocessableWhenSecondaryEmailAlreadyExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        SecondaryEmail::create([
            'user_id'     => $user->id,
            'email'       => $email = $this->faker->unique()->email,
            'verified_at' => now()
        ]);

        $this->addEmailToAccount(['email' => $email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function testWillReturnUnprocessableWhenEmailIsUserPrimaryEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $user->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function testWillReturnUnprocessableWhenEmailIsExistingUserPrimaryEmail(): void
    {
        [$johnny, $hector] = UserFactory::new()->count(2)->create()->all();

        Passport::actingAs($hector);

        $this->addEmailToAccount(['email' => $johnny->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }
}
