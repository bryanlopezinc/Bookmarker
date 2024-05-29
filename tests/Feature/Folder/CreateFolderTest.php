<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Filesystem\FoldersIconsFilesystem;
use App\FolderSettings\FolderSettings;
use App\Models\Folder;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Traits\ClearFoldersIconsStorage;

class CreateFolderTest extends TestCase
{
    use WithFaker;
    use Concerns\TestsFolderSettings;
    use ClearFoldersIconsStorage;

    protected function createFolderResponse(array $data = []): TestResponse
    {
        return $this->postJson(route('createFolder'), $data);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders', 'createFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->createFolderResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->createFolderResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->createFolderResponse(['name' => str_repeat('f', 51)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 50 characters.']);

        $this->createFolderResponse(['description' => str_repeat('f', 151)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description' => 'The description must not be greater than 150 characters.']);

        $this->createFolderResponse(['icon' => UploadedFile::fake()->create('photo.jpg', 2001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['icon' => 'The icon must not be greater than 2000 kilobytes.']);

        $this->createFolderResponse(['icon' => UploadedFile::fake()->create('photo.html', 1000)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['icon' => 'The icon must be an image.']);
    }

    public function testCreateFolder(): void
    {
        $filesystem = new FoldersIconsFilesystem();

        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name'         => $name = $this->faker->word,
            'description'  => $description = $this->faker->sentence,
            'icon'         => UploadedFile::fake()->image('folderIcon.jpg')->size(2000)
        ])->assertCreated();

        /** @var Folder */
        $folder = $user->folders->sole();

        $this->assertEquals($name, $folder->name->value);
        $this->assertEquals($description, $folder->description);
        $this->assertTrue($folder->visibility->isPublic());
        $this->assertEmpty($folder->settings->toArray());
        $this->assertTrue($filesystem->exists($folder->icon_path));
        $this->assertNotEquals($folder->public_id->value, $folder->public_id->present());
    }

    public function testCreateFolderWithoutDescription(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word])->assertCreated();

        /** @var Folder */
        $folder = $user->folders->sole();

        $this->assertNull($folder->description);
    }

    public function testCreatePublicFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'visibility' => 'public'])->assertCreated();

        /** @var Folder */
        $folder = $user->folders->sole();

        $this->assertTrue($folder->visibility->isPublic());
    }

    #[Test]
    public function createCollaboratorOnlyFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'visibility' => 'collaborators'])->assertCreated();

        /** @var Folder */
        $folder = $user->folders->sole();

        $this->assertTrue($folder->visibility->isVisibleToCollaboratorsOnly());
    }

    public function testCreatePrivateFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'visibility' => 'private'])
            ->assertCreated();

        /** @var Folder */
        $folder = $user->folders->sole();

        $this->assertTrue($folder->visibility->isPrivate());
    }

    #[Test]
    public function createPasswordProtectedFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'visibility' => 'password_protected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_password' => 'The folder password field is required.']);

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'visibility' => 'password_protected',
            'folder_password' => 'password',
        ])->assertCreated();

        /** @var Folder */
        $folder = $user->folders->sole();

        $this->assertTrue($folder->visibility->isPasswordProtected());
        $this->assertTrue(Hash::check('password', $folder->password));
    }

    #[Test]
    #[DataProvider('createFolderWithSettingsDataProvider')]
    public function createFolderWithSettings(array $settings): void
    {
        $user = UserFactory::new()->create();

        $this->loginUser($user);
        $this->createFolderResponse(['name' => $this->faker->word, 'settings' => Arr::undot($settings)])->assertCreated();

        /** @var Folder */
        $folder = $user->folders->sole();

        $this->assertEquals(
            $folder->settings->toArray(),
            (new FolderSettings($settings))->toArray()
        );
    }

    public static function createFolderWithSettingsDataProvider(): array
    {
        return [
            'max collaborators limit'                                => [['max_collaborators_limit' => '500']],
            'max_bookmarks_limit'                                    => [['max_bookmarks_limit' => '150']],
            'accept invite constraints'                              => [['accept_invite_constraints' => ['InviterMustHaveRequiredPermission']]],
            'enable notification: bool value'                         => [['notifications.enabled' => false]],
            'enable notification: string value'                       => [['notifications.enabled' => '0']],
            'enable notification: int value'                          => [['notifications.enabled' => 0]],
            'new collaborator notification: bool value'               => [['notifications.new_collaborator.enabled' => false]],
            'new collaborator notification: string value'             => [['notifications.new_collaborator.enabled' => '0']],
            'new collaborator notification: int value'                => [['notifications.new_collaborator.enabled' => 0]],
            'new bookmarks notification: bool value'                  => [['notifications.new_collaborator.enabled' => false]],
            'new bookmarks notification: string value'                => [['notifications.new_collaborator.enabled' => '0']],
            'new bookmarks notification: int value'                   => [['notifications.new_collaborator.enabled' => 0]],
            'bookmarks removed notification: bool value'              => [['notifications.bookmarks_removed.enabled' => false]],
            'bookmarks removed notification: string value'            => [['notifications.bookmarks_removed.enabled' => '0']],
            'bookmarks removed notification: int value'               => [['notifications.bookmarks_removed.enabled' => 0]],
            'collaborator exit notification: bool value'              => [['notifications.collaborator_exit.enabled' => false]],
            'collaborator exit notification: string value'            => [['notifications.collaborator_exit.enabled' => '0']],
            'collaborator exit notification: int value'               => [['notifications.collaborator_exit.enabled' => 0]],
            'new collaborator notification mode: all'                 => [['notifications.new_collaborator.mode' => '*']],
            'new collaborator notification mode: invitedByMe'         => [['notifications.new_collaborator.mode' => 'invitedByMe']],
            'collaborator exit notification mode: all'                => [['notifications.collaborator_exit.mode' => '*']],
            'collaborator exit notification mode: hasWritePermission' => [['notifications.collaborator_exit.mode' => 'hasWritePermission']],
            'many'                                                   => [
                [
                    'notifications.new_collaborator.enabled' => 0,
                    'notifications.collaborator_exit.enabled' => 0,
                    'notifications.folder_updated.enabled' => '0',
                    'notifications.new_bookmarks.enabled' => 1,
                    'activities.enabled' => '0'
                ],
            ]

        ];
    }

    #[Test]
    #[DataProvider('invalidSettingsData')]
    public function willReturnUnprocessableWhenFolderSettingsIsInValid(array $settings, array $errors): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'settings' => Arr::undot($settings)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings' => $errors]);
    }
}
