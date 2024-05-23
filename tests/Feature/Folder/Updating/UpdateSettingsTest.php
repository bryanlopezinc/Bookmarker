<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\Models\Folder;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use App\FolderSettings\FolderSettings;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Folder\Concerns\TestsFolderSettings;

class UpdateSettingsTest extends TestCase
{
    use CreatesCollaboration;
    use TestsFolderSettings;

    #[Test]
    #[DataProvider('invalidSettingsData')]
    public function willReturnUnprocessableWhenFolderSettingsIsInValid(array $settings, array $errors): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->updateFolderResponse(['folder_id' => $folder->id, 'settings' => Arr::undot($settings)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings' => $errors]);
    }

    #[Test]
    public function updateSettings(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()
            ->for($user)
            ->settings(FolderSettingsBuilder::new()->setMaxCollaboratorsLimit(450))
            ->create();

        $this->updateFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'settings' => ['notifications' => ['new_collaborator' => ['enabled' => 0]]]
        ])->assertOk();

        /** @var FolderSettings */
        $updatedFolderSettings = Folder::query()->whereKey($folder->id)->sole(['settings'])->settings;

        $this->assertEquals(450, $updatedFolderSettings->maxCollaboratorsLimit()->value());
        $this->assertTrue($updatedFolderSettings->newCollaboratorNotification()->isDisabled());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsUpdatingSettings(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        $this->loginUser($collaborator);

        $this->updateFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'settings'  => ['notifications' => ['new_collaborator' => ['enabled' => 0]]]
        ])->assertForbidden()->assertJsonFragment(['message' => 'CannotUpdateFolderAttribute']);
    }
}
