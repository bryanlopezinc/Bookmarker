<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Tag;
use App\Models\Taggable;
use Database\Factories\FolderFactory;
use Database\Factories\TagFactory;
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
            'description' => $description,
            'is_public' => false
        ]);
    }

    public function testMakeFolderPublic(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderIDs = FolderFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');

        $this->getTestResponse([
            'is_public' => true,
            'folder' => $folderIDs->first(),
            'name' => $this->faker->word,
        ])->assertStatus(423)
            ->assertExactJson(['message' => 'Password confirmation required.']);

        $this->getTestResponse([
            'is_public' => true,
            'folder' => $folderIDs->first(),
            'name' => $this->faker->word,
            'password' => 'password'
        ])->assertOk();

        //Assert subsequent request won't Require password
        $this->travel(52)->minutes(function () use ($folderIDs) {
            $folderIDs->skip(1)->each(function (int $folderID) {
                $this->getTestResponse([
                    'is_public' => true,
                    'folder' => $folderID,
                    'name' => $this->faker->word,
                ])->assertOk();
            });
        });

        $this->assertDatabaseHas(Folder::class, [
            'id' => $folderIDs->first(),
            'user_id' => $user->id,
            'is_public' => true
        ]);

        $this->travel(61)->minute(function () {
            $this->getTestResponse([
                'is_public' => true,
                'folder' => 11,
                'name' => $this->faker->word,
            ])->assertStatus(423);
        });
    }

    public function testWillReturnValidationErrorIfPasswordNoMatch(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'is_public' => true,
            'folder' => $folder->id,
            'name' => $this->faker->word,
            'password' => 'I forgot my password please let me in'
        ])->assertUnauthorized()
            ->assertExactJson(['message' => 'Invalid password']);
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

    public function testCanUpdateOnlyPrivacy(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'is_public' => true,
            'folder' => $folder->id,
            'password' => 'password'
        ])->assertOk();
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

    public function testCanUpdateFolderTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);
        $tags = TagFactory::new()->count(5)->create();

        $this->getTestResponse([
            'tags' => $tags->pluck('name')->implode(','),
            'folder' => $folder->id
        ])->assertOk();

        $tags->each(function (Tag $tag) use ($folder) {
            $this->assertDatabaseHas(Taggable::class, [
                'taggable_id' => $folder->id,
                'taggable_type' => Taggable::FOLDER_TYPE,
                'tag_id' => $tag->id
            ]);
        });
    }
    
    public function testCannotAttachExistingFolderTagsToFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);
        $tags = TagFactory::new()->count(6)->make()->pluck('name');

        $this->getTestResponse([
            'tags' => $tags->implode(','),
            'folder' => $folder->id
        ])->assertOk();

        $this->getTestResponse([
            'tags' => (string) $tags->random(),
            'folder' => $folder->id
        ])->assertStatus(409);
    }

    public function testCannotUpdateFolderTagsWhenFolderTagsIsGreaterThan_15(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->getTestResponse([
            'tags' => TagFactory::new()->count(15)->make()->pluck('name')->implode(','),
            'folder' => $folder->id
        ])->assertOk();

        $this->getTestResponse([
            'tags' => TagFactory::new()->count(2)->make()->pluck('name')->implode(','),
            'folder' => $folder->id
        ])->assertStatus(400);
    }

    public function testTagsMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'folder' => 300,
            'tags' => 'howTo,howTo,stackOverflow'
        ])->assertJsonValidationErrors([
            "tags.0" => [
                "The tags.0 field has a duplicate value."
            ],
            "tags.1" => [
                "The tags.1 field has a duplicate value."
            ]
        ]);
    }

    public function testFolderTagsCannotExceed_15(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'folder' => 300,
            'tags' => TagFactory::new()->count(16)->make()->pluck('name')->implode(',')
        ])->assertJsonValidationErrors([
            "tags" => ['The tags must not be greater than 15 characters.']
        ]);
    }
}
