<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\UserFoldersCount;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreateFolderTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders', 'createFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrors(['name']);
        $this->getTestResponse([
            'name' => ' ',
        ])->assertJsonValidationErrors(['name']);
    }

    public function testFolderNameCannotBeGreaterThan_50(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['name' => str_repeat('f', 51)])->assertJsonValidationErrors([
            'name' => 'The name must not be greater than 50 characters.'
        ]);
    }

    public function testFolderDescriptionCannotExceed_150(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['description' => str_repeat('f', 151)])->assertJsonValidationErrors([
            'description' => 'The description must not be greater than 150 characters.'
        ]);
    }

    public function testCreateFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse([
            'name' => $name = $this->faker->word,
            'description' => $description = $this->faker->sentence
        ])->assertCreated();

        $this->assertDatabaseHas(Folder::class, [
            'user_id' => $user->id,
            'name' => $name,
            'description' => $description
        ]);

        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserFoldersCount::TYPE
        ]);
    }

    public function testCanCreateFolderWithoutDescription(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'name' => $this->faker->word,
        ])->assertCreated();
    }
}
