<?php

namespace Tests\Feature\Folder;

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

    protected function updateFolderResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(route('updateFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders', 'updateFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->updateFolderResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->updateFolderResponse()->assertJsonValidationErrors(['name', 'folder']);
        $this->updateFolderResponse(['folder' => 33])->assertJsonValidationErrors(['name']);

        $this->updateFolderResponse([
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

        $this->updateFolderResponse(['name' => str_repeat('f', 51)])->assertJsonValidationErrors([
            'name' => 'The name must not be greater than 50 characters.'
        ]);
    }

    public function testFolderDescriptionCannotExceed_150(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->updateFolderResponse(['description' => str_repeat('f', 151)])->assertJsonValidationErrors([
            'description' => 'The description must not be greater than 150 characters.'
        ]);
    }

    public function testUpdateFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Folder */
        $model = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->updateFolderResponse([
            'name' => $name = $this->faker->word,
            'description' => $description = $this->faker->sentence,
            'folder' => $model->id
        ])->assertOk();

        /** @var Folder */
        $folder = Folder::query()->whereKey($model->id)->first();

        $this->assertEquals($name, $folder->name);
        $this->assertEquals($description, $folder->description);
        $this->assertFalse($folder->is_public);
        $this->assertEquals($model->created_at->timestamp, $folder->created_at->timestamp);
    }

    public function testMakeFolderPublic(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderIDs = FolderFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');

        $this->updateFolderResponse([
            'is_public' => true,
            'folder' => $folderIDs->first(),
            'name' => $this->faker->word,
        ])->assertStatus(423)
            ->assertExactJson(['message' => 'Password confirmation required.']);

        $this->updateFolderResponse([
            'is_public' => true,
            'folder' => $folderIDs->first(),
            'name' => $this->faker->word,
            'password' => 'password'
        ])->assertOk();

        //Assert subsequent request won't Require password
        $this->travel(52)->minutes(function () use ($folderIDs) {
            $folderIDs->skip(1)->each(function (int $folderID) {
                $this->updateFolderResponse([
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
            $this->updateFolderResponse([
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

        $this->updateFolderResponse([
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

        /** @var Folder */
        $model = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->updateFolderResponse([
            'description' => '',
            'folder' => $model->id
        ])->assertOk();

        /** @var Folder */
        $folder = Folder::query()->whereKey($model->id)->first();

        $this->assertEquals($model->name, $folder->name);
        $this->assertNull($folder->description);
        $this->assertFalse($folder->is_public);
        $this->assertEquals($model->created_at->timestamp, $folder->created_at->timestamp);
    }

    public function testCanUpdateOnlyPrivacy(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Folder */
        $model = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->updateFolderResponse([
            'is_public' => true,
            'folder' => $model->id,
            'password' => 'password'
        ])->assertOk();

        /** @var Folder */
        $folder = Folder::query()->whereKey($model->id)->first();

        $this->assertEquals($model->name, $folder->name);
        $this->assertEquals($folder->description, $model->description);
        $this->assertTrue($folder->is_public);
        $this->assertEquals($model->created_at->timestamp, $folder->created_at->timestamp);
    }

    public function testCanUpdateOnlyDescription(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Folder */
        $model = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->updateFolderResponse([
            'description' => $description = $this->faker->sentence,
            'folder' => $model->id
        ])->assertOk();

        /** @var Folder */
        $folder = Folder::query()->whereKey($model->id)->first();

        $this->assertEquals($model->name, $folder->name);
        $this->assertEquals($description, $folder->description);
        $this->assertFalse($folder->is_public);
        $this->assertEquals($model->created_at->timestamp, $folder->created_at->timestamp);
    }

    public function testCanUpdateOnlyName(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Folder */
        $model = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->updateFolderResponse([
            'name' => $name = $this->faker->word,
            'folder' => $model->id
        ])->assertOk();

        /** @var Folder */
        $folder = Folder::query()->whereKey($model->id)->first();

        $this->assertEquals($name, $folder->name);
        $this->assertEquals($folder->description, $model->description);
        $this->assertFalse($folder->is_public);
        $this->assertEquals($model->created_at->timestamp, $folder->created_at->timestamp);
    }

    public function testCanUpdateOnlyOwnFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'folder' => $folder->id
        ])->assertForbidden();
    }

    public function testCannotUpdateInvalidFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'folder' => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson(['message' => "The folder does not exists"]);
    }

    public function testCanUpdateFolderTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Folder */
        $model = FolderFactory::new()->create(['user_id' => $user->id]);
        $tags = TagFactory::new()->count(5)->create(['created_by' => $user->id]);

        $this->updateFolderResponse([
            'tags' => $tags->pluck('name')->implode(','),
            'folder' => $model->id
        ])->assertOk();

        $tags->each(function (Tag $tag) use ($model) {
            $this->assertDatabaseHas(Taggable::class, [
                'taggable_id' => $model->id,
                'taggable_type' => Taggable::FOLDER_TYPE,
                'tag_id' => $tag->id
            ]);
        });

        /** @var Folder */
        $folder = Folder::query()->whereKey($model->id)->first();

        $this->assertEquals($model->name, $folder->name);
        $this->assertEquals($model->description, $folder->description);
        $this->assertFalse($folder->is_public);
        $this->assertEquals($model->created_at->timestamp, $folder->created_at->timestamp);
    }

    public function testCannotAttachExistingFolderTagsToFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);
        $tags = TagFactory::new()->count(6)->make()->pluck('name');

        $this->updateFolderResponse([
            'tags' => $tags->implode(','),
            'folder' => $folder->id
        ])->assertOk();

        $this->updateFolderResponse([
            'tags' => (string) $tags->random(),
            'folder' => $folder->id
        ])->assertStatus(409);
    }

    public function testCannotUpdateFolderTagsWhenFolderTagsIsGreaterThan_15(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->updateFolderResponse([
            'tags' => TagFactory::new()->count(15)->make()->pluck('name')->implode(','),
            'folder' => $folder->id
        ])->assertOk();

        $this->updateFolderResponse([
            'tags' => TagFactory::new()->count(2)->make()->pluck('name')->implode(','),
            'folder' => $folder->id
        ])->assertStatus(400);
    }

    public function testTagsMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->updateFolderResponse([
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

        $this->updateFolderResponse([
            'folder' => 300,
            'tags' => TagFactory::new()->count(16)->make()->pluck('name')->implode(',')
        ])->assertJsonValidationErrors([
            "tags" => ['The tags must not be greater than 15 characters.']
        ]);
    }

    public function testWillNotReturnStaleData(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Folder */
        $model = FolderFactory::new()->create(['user_id' => $user->id]);

        //should cache folder.
        $this->getJson(route('fetchFolder', ['id' => $model->id]))
            ->assertOk()
            ->assertJsonFragment([
                'name' => $model->name,
                'description' => $model->description
            ]);

        $this->updateFolderResponse([
            'name' => $name = $this->faker->word,
            'description' => $description = $this->faker->sentence,
            'folder' => $model->id
        ])->assertOk();

        $this->getJson(route('fetchFolder', ['id' => $model->id]))
            ->assertOk()
            ->assertJsonFragment([
                'name' => $name,
                'description' => $description
            ]);
    }
}
