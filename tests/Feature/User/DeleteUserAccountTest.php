<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use Tests\TestCase;
use App\Models\User;
use Laravel\Passport\Passport;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Illuminate\Foundation\Testing\WithFaker;

class DeleteUserAccountTest extends TestCase
{
    use WithFaker;

    protected function deleteAccountResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->deleteJson(route('deleteUserAccount'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users', 'deleteUserAccount');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteAccountResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenPasswordParameterIsNotPresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteAccountResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function testDeleteUser(): void
    {
        $user = UserFactory::new()->create();

        Passport::actingAs($user);

        $this->deleteAccountResponse(['password' => 'password'])->assertOk();

        $this->assertDatabaseMissing(User::class, ['id' => $user->id]);
    }

    public function testWillReturnBadRequestWhenPasswordIsIncorrect(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteAccountResponse(['password' => 'or 1=1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'InvalidPassword']);
    }
}
