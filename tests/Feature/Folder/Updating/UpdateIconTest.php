<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\DataTransferObjects\Activities\FolderIconChangedActivityLogData;
use App\Enums\ActivityType;
use App\Enums\CollaboratorMetricType;
use App\Enums\Permission;
use Illuminate\Support\Str;
use Tests\Traits\CreatesRole;
use Illuminate\Http\UploadedFile;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use App\Filesystem\FoldersIconsFilesystem;
use App\FolderSettings\Settings\Activities\LogActivities;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\Traits\ClearFoldersIconsStorage;
use Tests\Traits\GeneratesId;

class UpdateIconTest extends TestCase
{
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;
    use GeneratesId;
    use ClearFoldersIconsStorage;

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser();

        $id = $this->generateFolderId()->present();

        $this->updateFolderResponse(['icon' => UploadedFile::fake()->create('photo.jpg', 2001), 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['icon' => 'The icon must not be greater than 2000 kilobytes.']);

        $this->updateFolderResponse(['icon' => UploadedFile::fake()->create('photo.html', 1000), 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['icon' => 'The icon must be an image.']);
    }

    #[Test]
    public function updateIcon(): void
    {
        $filesystem = new FoldersIconsFilesystem();
        $newIconPath = Str::random(40);
        $initialIcon = Str::random(40) . '.jpg';

        Str::createRandomStringsUsing(fn () => $newIconPath);

        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->hasCustomIcon($initialIcon)->for($user)->create();

        $this->updateFolderResponse([
            'icon'      => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();
        $this->assertEquals($activity->type, ActivityType::ICON_CHANGED);
        $this->assertEquals($activity->data, (new FolderIconChangedActivityLogData($user))->toArray());

        $this->assertUpdated($folder, ['icon_path' => "{$newIconPath}.jpg"]);
        $this->assertFalse($filesystem->exists($initialIcon));
        $this->assertTrue($filesystem->exists("{$newIconPath}.jpg"));
    }

    #[Test]
    public function whenFolderHasNoIcon(): void
    {
        $filesystem = new FoldersIconsFilesystem();

        $newIconPath = Str::random(40);

        Str::createRandomStringsUsing(fn () => $newIconPath);

        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->updateFolderResponse([
            'icon'      => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        $this->assertUpdated($folder, ['icon_path' => "{$newIconPath}.jpg"]);
        $this->assertTrue($filesystem->exists($folder->refresh()->icon_path));
    }

    #[Test]
    public function removeIcon(): void
    {
        $user = UserFactory::new()->create();
        $filesystem = new FoldersIconsFilesystem();
        $newIconPath = Str::random(40);

        Str::createRandomStringsUsing(fn () => $newIconPath);

        $iconPath = $filesystem->store(UploadedFile::fake()->image('photo.jpg'));
        $this->assertTrue($filesystem->exists($iconPath));

        $folder = FolderFactory::new()->hasCustomIcon($iconPath)->for($user)->create();

        $this->loginUser($user);
        $this->updateFolderResponse([
            'icon'      => null,
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        $this->assertUpdated($folder, ['icon_path' => null]);
        $this->assertNull($folder->refresh()->icon_path);
        $this->assertFalse($filesystem->exists($iconPath));
        $this->assertNull($folder->icon_path);
    }

    #[Test]
    public function whenCollaboratorUpdatesIcon(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_ICON);

        $this->loginUser($collaborator);

        $this->updateFolderResponse([
            'icon'      => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::UPDATES);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type);

        $this->updateFolderResponse([
            'icon'      => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);
    }

    #[Test]
    public function collaboratorWithRoleCanUpdateIcon(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: [Permission::INVITE_USER, Permission::UPDATE_FOLDER_ICON]));

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'icon'      => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
        ])->assertOk();
    }

    #[Test]
    public function willNotLogActivityWhenActivityLoggingIsDisabled(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new LogActivities(false))
            ->create(['name' => 'foo', 'description' => 'bar']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'icon'      => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
        ])->assertOk();

        $this->refreshApplication();
        $this->loginUser($folderOwner);
        $this->updateFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'icon'      => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
        ])->assertOk();

        $this->assertCount(0, $folder->activities);
    }
}
