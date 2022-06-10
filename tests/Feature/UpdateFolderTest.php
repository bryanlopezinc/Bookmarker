<?php

namespace Tests\Feature;

use App\Models\Folder;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UpdateFolderTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(route('updateFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders', 'updateFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrors(['name', 'folder']);
        $this->getTestResponse(['folder' => 33])->assertJsonValidationErrors(['name']);

        $this->getTestResponse([
            'folder' => 33,
            'name' => '',
            'description' => 'foo'
        ])->assertJsonValidationErrors([
            'name' => [
                'The name field must have a value.'
            ]
        ]);
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

    public function testUpdateFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'name' => $name = $this->faker->word,
            'description' => $description = $this->faker->sentence,
            'folder' => $folder->id
        ])->assertOk();

        $this->assertDatabaseHas(Folder::class, [
            'id' => $folder->id,
            'user_id' => $user->id,
            'name' => $name,
            'description' => $description
        ]);
    }

    public function testCanUpdateDescriptionToBeBlank(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'description' => '',
            'folder' => $folder->id
        ])->assertOk();

        $this->assertDatabaseHas(Folder::class, [
            'id' => $folder->id,
            'user_id' => $user->id,
            'name' => $folder->name,
            'description' => null
        ]);
    }

    public function testCanUpdateOnlyDescription(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'description' => $description = $this->faker->sentence,
            'folder' => $folder->id
        ])->assertOk();

        $this->assertDatabaseHas(Folder::class, [
            'id' => $folder->id,
            'user_id' => $user->id,
            'name' => $folder->name,
            'description' => $description
        ]);
    }

    public function testCanUpdateOnlyName(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'name' => $name = $this->faker->word,
            'folder' => $folder->id
        ])->assertOk();

        $this->assertDatabaseHas(Folder::class, [
            'id' => $folder->id,
            'user_id' => $user->id,
            'name' => $name,
            'description' => $folder->description
        ]);
    }

    public function testCanUpdateOnlyOwnFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->getTestResponse([
            'name' => $this->faker->word,
            'folder' => $folder->id
        ])->assertForbidden();
    }

    public function testCannotUpdateInvalidFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'name' => $this->faker->word,
            'folder' => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson(['message' => "The folder does not exists"]);
    }
}
