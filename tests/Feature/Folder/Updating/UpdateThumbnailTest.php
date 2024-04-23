<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\Enums\CollaboratorMetricType;
use App\Enums\Permission;
use Illuminate\Support\Str;
use Tests\Traits\CreatesRole;
use Illuminate\Http\UploadedFile;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use App\Filesystem\FolderThumbnailFileSystem;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\Traits\GeneratesId;

class UpdateThumbnailTest extends TestCase
{
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;
    use GeneratesId;

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser();

        $id = $this->generateFolderId()->present();

        $this->updateFolderResponse(['thumbnail' => UploadedFile::fake()->create('photo.jpg', 2001), 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['thumbnail' => 'The thumbnail must not be greater than 2000 kilobytes.']);

        $this->updateFolderResponse(['thumbnail' => UploadedFile::fake()->create('photo.html', 1000), 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['thumbnail' => 'The thumbnail must be an image.']);
    }

    #[Test]
    public function updateThumbnail(): void
    {
        $filesystem = new FolderThumbnailFileSystem();
        $newIconPath = Str::random(40);
        $initialIcon = Str::random(40) . 'jpg';

        Str::createRandomStringsUsing(fn () => $newIconPath);

        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->hasCustomIcon($initialIcon)->for($user)->create();

        $this->updateFolderResponse([
            'thumbnail' => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        $this->assertUpdated($folder, ['icon_path' => "{$newIconPath}.jpg"]);
        $this->assertFalse($filesystem->exists($initialIcon));
        $this->assertTrue($filesystem->exists("{$newIconPath}.jpg"));
    }

    #[Test]
    public function whenFolderHasNoThumbnail(): void
    {
        $filesystem = new FolderThumbnailFileSystem();

        $newIconPath = Str::random(40);

        Str::createRandomStringsUsing(fn () => $newIconPath);

        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->updateFolderResponse([
            'thumbnail' => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        $this->assertUpdated($folder, ['icon_path' => "{$newIconPath}.jpg"]);
        $this->assertTrue($filesystem->exists($folder->refresh()->icon_path));
    }

    #[Test]
    public function removeThumbnail(): void
    {
        $user = UserFactory::new()->create();
        $filesystem = new FolderThumbnailFileSystem();
        $newIconPath = Str::random(40);

        Str::createRandomStringsUsing(fn () => $newIconPath);

        $iconPath = $filesystem->store(UploadedFile::fake()->image('photo.jpg'));
        $this->assertTrue($filesystem->exists($iconPath));

        $folder = FolderFactory::new()->hasCustomIcon($iconPath)->for($user)->create();

        $this->loginUser($user);
        $this->updateFolderResponse([
            'thumbnail' => null,
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        $this->assertUpdated($folder, ['icon_path' => null]);
        $this->assertNull($folder->refresh()->icon_path);
        $this->assertFalse($filesystem->exists($iconPath));
        $this->assertNull($folder->icon_path);
    }

    #[Test]
    public function whenCollaboratorUpdatesThumbnail(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_THUMBNAIL);

        $this->loginUser($collaborator);

        $this->updateFolderResponse([
            'thumbnail' => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::UPDATES);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type);

        $this->updateFolderResponse([
            'thumbnail' => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);
    }

    #[Test]
    public function collaboratorWithRoleCanUpdateThumbnail(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: [Permission::INVITE_USER, Permission::UPDATE_FOLDER_THUMBNAIL]));

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'thumbnail' => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
        ])->assertOk();
    }
}
