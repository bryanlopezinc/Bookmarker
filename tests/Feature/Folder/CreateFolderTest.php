<?php

namespace Tests\Feature\Folder;

use App\Models\Folder;
use App\Models\Taggable;
use App\Models\UserFoldersCount;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreateFolderTest extends TestCase
{
    use WithFaker;

    protected function createFolderResponse(array $data = []): TestResponse
    {
        return $this->postJson(route('createFolder'), $data);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders', 'createFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->createFolderResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFolderResponse()->assertJsonValidationErrors(['name']);
    }

    public function testFolderNameMustNotBeEmpty(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => ' ',
        ])->assertJsonValidationErrors(['name']);
    }

    public function testFolderNameCannotBeGreaterThan_50(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFolderResponse(['name' => str_repeat('f', 51)])
            ->assertJsonValidationErrors([
                'name' => 'The name must not be greater than 50 characters.'
            ]);
    }

    public function testFolderDescriptionCannotBeGreaterThan_150(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFolderResponse(['description' => str_repeat('f', 151)])
            ->assertJsonValidationErrors([
                'description' => 'The description must not be greater than 150 characters.'
            ]);
    }

    public function testCreateFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $name = $this->faker->word,
            'description' => $description = $this->faker->sentence
        ])->assertCreated();

        /** @var Folder */
        $folder = Folder::query()->where('user_id', $user->id)->sole();

        $this->assertEquals($name, $folder->name);
        $this->assertEquals($description, $folder->description);
        $this->assertFalse($folder->is_public);
        $this->assertTrue($folder->created_at->isSameMinute());
        $this->assertTrue($folder->updated_at->isSameMinute());

        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserFoldersCount::TYPE
        ]);

        $this->assertDatabaseMissing(Taggable::class, [
            'taggable_id' => $folder->id,
        ]);
    }

    public function testCanCreateFolderWithoutDescription(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
        ])->assertCreated();

        /** @var Folder */
        $folder = Folder::query()->where('user_id', $user->id)->sole();

        $this->assertNull($folder->description);
    }

    public function testCreatePublicFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'is_public' => true
        ])->assertCreated();

        $this->assertDatabaseHas(Folder::class, [
            'user_id' => $user->id,
            'is_public' => true
        ]);
    }

    public function testFolderTagsMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
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

    public function testFolderTagsCannotBeGreaterThan_15(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'tags' => TagFactory::new()->count(16)->make()->pluck('name')->implode(',')
        ])->assertJsonValidationErrors([
            "tags" => ['The tags must not be greater than 15 characters.']
        ]);
    }

    public function testCanCreateFolderWithTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'tags' => TagFactory::new()->count(15)->make()->pluck('name')->implode(',')
        ])->assertCreated();

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => Folder::query()->where('user_id', $user->id)->sole('id')->id,
            'taggable_type' => Taggable::FOLDER_TYPE,
        ]);
    }
}
