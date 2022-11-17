<?php

namespace Tests\Feature\Folder;

use App\DataTransferObjects\FolderSettings;
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
        $this->assertEquals($folder->settings, FolderSettings::default()->toArray());

        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserFoldersCount::TYPE
        ]);

        $this->assertDatabaseMissing(Taggable::class, [
            'taggable_id' => $folder->id,
            'taggable_type' => Taggable::FOLDER_TYPE
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

    public function testCanDisableNotifications(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => ['N-enable' => false]
        ])->assertCreated();

        $settings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        $this->assertFalse($settings['notifications']['enabled']);
    }

    public function testCanDisableNewCollaboratorNotifications(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => ['N-newCollaborator' => false]
        ])->assertCreated();

        $settings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        $this->assertFalse($settings['notifications']['newCollaborator']['notify']);
    }

    public function testCanDisableFolderUpdatedNotification(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => ['N-updated' => false]
        ])->assertCreated();

        $settings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        $this->assertFalse($settings['notifications']['updated']);
    }

    public function testCanDisableBookmarkAddedNotification(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => ['N-newBookmarks' => false]
        ])->assertCreated();

        $settings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        $this->assertFalse($settings['notifications']['bookmarksAdded']);
    }

    public function testCanDisableBookmarkRemovedNotification(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => ['N-bookmarkDelete' => false]
        ])->assertCreated();

        $settings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        $this->assertFalse($settings['notifications']['bookmarksRemoved']);
    }

    public function testCanDisableCollaboratorExitNotification(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => ['N-collaboratorExit' => false]
        ])->assertCreated();

        $settings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        $this->assertFalse($settings['notifications']['collaboratorExit']['notify']);
    }

    public function testCanEnable_onlyCollaboratorsInvitedByMe_Notification(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => ['N-onlyNewCollaboratorsByMe' => false]
        ])->assertCreated();

        $settings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        $this->assertFalse($settings['notifications']['newCollaborator']['onlyCollaboratorsInvitedByMe']);
    }

    public function testCanEnable_collaboratorExitOnlyHasWritePermission_Notification(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => ['N-collaboratorExitOnlyHasWritePermission' => false]
        ])->assertCreated();

        $settings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        $this->assertFalse($settings['notifications']['collaboratorExit']['onlyWhenCollaboratorHasWritePermission']);
    }

    public function testCanHaveMultipleSettings(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => [
                'N-newCollaborator' => false,
                'N-updated' => false,
                'N-collaboratorExit' => false
            ]
        ])->assertCreated();

        $settings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        $this->assertFalse($settings['notifications']['collaboratorExit']['notify']);
        $this->assertFalse($settings['notifications']['updated']);
        $this->assertFalse($settings['notifications']['newCollaborator']['notify']);
    }

    public function testFolderSettingsMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->make());

        //Assert settings must be an array
        $this->createFolderResponse(['settings' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'settings' => ['The settings must be an array.']
            ]);

        //Assert settings cannot be empty
        $this->createFolderResponse(['settings' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'settings' => ['The settings field must have a value.']
            ]);

        //Assert settings must have a valid value and settings.N-enable must be a boolean
        $this->createFolderResponse([
            'settings' => [
                'foo' => false,
                'N-enable' => 'foo',
            ]
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'settings' => [
                    "The settings.N-enable field must be true or false.",
                    "The selected settings.foo is invalid."
                ]
            ]);

        //Assert all values must be a boolean.
        $this->createFolderResponse([
            'settings' => [
                'N-newCollaborator' => 'foo',
                'N-onlyNewCollaboratorsByMe' => 'foo',
                'N-updated' => 'foo',
                'N-newBookmarks' => 'foo',
                'N-bookmarkDelete' => 'foo',
                'N-collaboratorExit' => 'foo',
                'N-collaboratorExitOnlyHasWritePermission' => 'foo'
            ]
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['settings' => [
                "The settings.N-newCollaborator field must be true or false.",
                "The settings.N-onlyNewCollaboratorsByMe field must be true or false.",
                "The settings.N-updated field must be true or false.",
                "The settings.N-newBookmarks field must be true or false.",
                "The settings.N-bookmarkDelete field must be true or false.",
                "The settings.N-collaboratorExit field must be true or false.",
                "The settings.N-collaboratorExitOnlyHasWritePermission field must be true or false."
            ]]);

        //Assert other values should not be present when notification is disabled.
        $this->createFolderResponse([
            'settings' => [
                'N-enable' => false,
                'N-newCollaborator' => true,
                'N-onlyNewCollaboratorsByMe' => false,
                'N-updated' => true,
                'N-newBookmarks' => true,
                'N-bookmarkDelete' => true,
                'N-collaboratorExit' => true,
                'N-collaboratorExitOnlyHasWritePermission' => true
            ]
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['settings' => [
                "The settings.N-newCollaborator field is prohibited.",
                "The settings.N-onlyNewCollaboratorsByMe field is prohibited.",
                "The settings.N-updated field is prohibited.",
                "The settings.N-newBookmarks field is prohibited.",
                "The settings.N-bookmarkDelete field is prohibited.",
                "The settings.N-collaboratorExit field is prohibited.",
                "The settings.N-collaboratorExitOnlyHasWritePermission field is prohibited."
            ]]);

        //Assert N-onlyNewCollaboratorsByMe setting cannot be true when N-newCollaborator value is false.
        $this->createFolderResponse([
            'name' => $this->faker->word,
            'settings' => [
                'N-enable' => true,
                'N-newCollaborator' => false,
                'N-onlyNewCollaboratorsByMe' => true,
            ]
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'settings' => [
                    "The settings N-onlyNewCollaboratorsByMe cannot be true when N-newCollaborator is false.",
                ]
            ]);

        //Assert N-collaboratorExitOnlyHasWritePermission setting cannot be true when N-collaboratorExit value is false.
        $this->createFolderResponse([
            'settings' => [
                'N-enable' => true,
                'N-collaboratorExit' => false,
                'N-collaboratorExitOnlyHasWritePermission' => true
            ]
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['settings' => [
                "The settings N-collaboratorExitOnlyHasWritePermission cannot be true when N-collaboratorExit is false.",
            ]]);
    }
}
