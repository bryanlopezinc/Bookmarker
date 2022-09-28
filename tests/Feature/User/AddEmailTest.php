<?php

namespace Tests\Feature\User;

use App\Mail\VerificationCodeMail;
use App\Models\SecondaryEmail;
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
        $this->assertRouteIsAccessibeViaPath('v1/emails/add', 'addEmailToAccount');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->addEmailToAccount()->assertUnauthorized();
    }

    public function testAttrbutesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount()->assertJsonValidationErrorFor('email');
    }

    public function testAttrbutesMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount(['email' => 'foo bar'])->assertJsonValidationErrorFor('email');
    }

    public function testAddEmail(): void
    {
        Mail::fake();

        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();

        Mail::assertQueued(VerificationCodeMail::class);
    }

    public function testCanAddAnotherEmailAfter_5_minutes_WithoutVerifyingFirstEmail(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();

        $this->travel(6)->minutes(function () {
            $this->addEmailToAccount(['email' => $this->faker->unique()->email])->assertOk();
        });
    }

    public function testCannotHaveMoreThan_3_SecondaryEmails(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        for ($i = 0; $i < 3; $i++) {
            SecondaryEmail::create([
                'user_id' => $user->id,
                'email' => $this->faker->unique()->email,
                'verified_at' => now()
            ]);
        }

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Max emails reached',
                'error_code' => 142
            ]);
    }

    public function testCannotHaveMoreThan_1_EmailAwaitingVerification(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $this->faker->unique()->email]);

        $this->addEmailToAccount(['email' => $this->faker->unique()->email])
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson([
                'message' => 'Verify email',
                'error_code' => 3118
            ]);
    }

    public function testCannotAddExistingSecondaryEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        SecondaryEmail::create([
            'user_id' => $user->id,
            'email' => $email = $this->faker->unique()->email,
            'verified_at' => now()
        ]);

        $this->addEmailToAccount(['email' => $email])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson([
                'message' => 'Email already added',
                'error_code' => 3448
            ]);
    }

    public function testCannotAddOwnPrimaryEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->addEmailToAccount(['email' => $user->email])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson([
                'message' => 'Cannot add primary email',
                'error_code' => 3082
            ]);
    }

    public function testCannotAddExistingUserPrimaryEmail(): void
    {
        [$johnny, $hector] = UserFactory::new()->count(2)->create()->all();

        Passport::actingAs($hector);

        $this->addEmailToAccount(['email' => $johnny->email])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Email already exists',
                'error_code' => 333
            ]);
    }

    public function testCannotAddExistingUserSecondaryEmail(): void
    {
        [$tony, $mark] = UserFactory::new()->count(2)->create()->all();

        SecondaryEmail::create([
            'user_id' => $mark->id,
            'email' => $marksSecondaryEmail = $this->faker->unique()->email,
            'verified_at' => now()
        ]);

        Passport::actingAs($tony);

        $this->addEmailToAccount(['email' => $marksSecondaryEmail])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Email already exists',
                'error_code' => 333
            ]);
    }
}
