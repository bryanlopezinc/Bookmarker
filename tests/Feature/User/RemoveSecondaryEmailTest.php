<?php

namespace Tests\Feature\User;

use App\Models\SecondaryEmail;
use Database\Factories\EmailFactory;
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

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->removeEmailResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeEmailResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');

        $this->removeEmailResponse(['email' => 'foo bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function testRemoveEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $emails = EmailFactory::times(2)->for($user)->create()->pluck('email');

        $this->removeEmailResponse(['email' => $emails[0]])->assertOk();

        $this->assertDatabaseMissing(SecondaryEmail::class, [
            'user_id' => $user->id,
            'email'   => $emails[0],
        ]);

        $this->assertDatabaseHas(SecondaryEmail::class, [
            'user_id' => $user->id,
            'email'   => $emails[1],
        ]);
    }

    public function testWillReturnNotFoundWhenEmailIsNotAttachedToUserAccount(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeEmailResponse(['email' => $this->faker->unique()->email])
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertExactJson(['message' => 'EmailNotFound']);

        $email = EmailFactory::new()->create()->email;

        $this->removeEmailResponse(['email' => $email])
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertExactJson(['message' => 'EmailNotFound']);
    }

    public function testWillReturnBadRequestWhenUserIsRemovingPrimaryEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->removeEmailResponse(['email' => $user->email])
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertExactJson(['message' => 'CannotRemovePrimaryEmail']);
    }
}
