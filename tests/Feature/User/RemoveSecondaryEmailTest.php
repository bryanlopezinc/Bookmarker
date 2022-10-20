<?php

namespace Tests\Feature\User;

use App\Models\SecondaryEmail;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RemoveSecondaryEmailTest extends TestCase
{
    use WithFaker;

    protected function removeEmailResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('removeEmailFromAccount'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/emails/remove', 'removeEmailFromAccount');
    }

    public function testUnAuthorizedUserCannotAccessEndPoint(): void
    {
        $this->removeEmailResponse()->assertUnauthorized();
    }

    public function testEmailAttributeMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeEmailResponse()->assertJsonValidationErrorFor('email');
    }

    public function testEmailMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeEmailResponse(['email' => 'foo bar'])->assertJsonValidationErrorFor('email');
    }

    public function testRemoveEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        SecondaryEmail::query()->create([
            'user_id' => $user->id,
            'email' => $email = $this->faker->unique()->email,
            'verified_at' => now()
        ]);

        $this->removeEmailResponse(['email' => $email])->assertOk();

        $this->assertDatabaseMissing(SecondaryEmail::class, [
            'user_id' => $user->id,
            'email' => $email,
        ]);
    }

    public function testWillRemoveOnlyOneEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        SecondaryEmail::insert([
            [
                'user_id' => $user->id,
                'email' => $firstEmail = $this->faker->unique()->email,
                'verified_at' => now()
            ],
            [
                'user_id' => $user->id,
                'email' => $secondEmail = $this->faker->unique()->email,
                'verified_at' => now()
            ]
        ]);

        $this->removeEmailResponse(['email' => $firstEmail])->assertOk();

        $userEmails = SecondaryEmail::query()->where('user_id', $user->id)->get();

        $this->assertCount(1, $userEmails);
        $this->assertEquals($secondEmail, $userEmails->first()->email);
    }

    public function testEmailMustExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeEmailResponse([
            'email' => $this->faker->unique()->email
        ])->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertExactJson([
                'message' => 'Email does not exist'
            ]);
    }

    public function testSecondaryEmailMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        SecondaryEmail::query()->create([
            'user_id' => $otherUserID = UserFactory::new()->create()->id,
            'email' => $email = $this->faker->unique()->email,
            'verified_at' => now()
        ]);

        $this->removeEmailResponse([
            'email' => $email
        ])->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertExactJson([
                'message' => 'Email does not exist'
            ]);

        $this->assertDatabaseHas(SecondaryEmail::class, [
            'email' => $email,
            'user_id' => $otherUserID
        ]);
    }

    public function testCannotRemovePrimaryEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->removeEmailResponse(['email' => $user->email])
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson([
                'message' => 'Cannot remove primary email'
            ]);

        $this->assertDatabaseHas(User::class, ['id' => $user->id]);
    }

    public function testCannotRemoveAnotherUsersPrimaryEmail(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeEmailResponse([
            'email' => $email = UserFactory::new()->create()->email
        ])->assertNotFound();

        $this->assertDatabaseHas(User::class, ['email' => $email]);
    }
}
