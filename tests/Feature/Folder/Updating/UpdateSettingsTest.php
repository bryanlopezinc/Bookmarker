<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\Models\Folder;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use App\ValueObjects\FolderSettings;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use Tests\Feature\Folder\Concerns\TestsFolderSettings;

class UpdateSettingsTest extends TestCase
{
    use CreatesCollaboration;
    use TestsFolderSettings;

    #[Test]
    public function willReturnUnprocessableWhenFolderSettingsIsInValid(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->assertWillReturnUnprocessableWhenFolderSettingsIsInValid(
            ['folder_id' => $folder->id],
            function (array $parameters) {
                return $this->updateFolderResponse($parameters);
            }
        );
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

        $this->assertEquals(450, $updatedFolderSettings->maxCollaboratorsLimit);
        $this->assertTrue($updatedFolderSettings->newCollaboratorNotificationIsDisabled);
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
        ])->assertForbidden()->assertJsonFragment($error = ['message' => 'CannotUpdateFolderAttribute']);
    }
}
