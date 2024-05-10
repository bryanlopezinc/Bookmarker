<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\Actions\ToggleFolderFeature;
use App\Enums\Feature;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;

class DisabledFeatureTest extends TestCase
{
    use CreatesCollaboration;
    use WithFaker;

    #[Test]
    public function whenUpdateFolderNameFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo', 'description' => 'foo bar folder']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        $updateCollaboratorActionService->disable($folder->id, Feature::UPDATE_FOLDER_NAME);

        $this->loginUser($collaborator);
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

        $this->updateFolderResponse(['description' => 'foo', 'folder_id' => $folder->public_id->present()])->assertOk();

        $this->loginUser($folderOwner);
        $this->updateFolderResponse(['name' => 'baz', 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->updateFolderResponse(['description' => 'baz and foo', 'folder_id' => $folder->public_id->present()])->assertOk();
    }

    #[Test]
    public function whenUpdateFolderDescriptionFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo', 'description' => 'foo bar folder']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        $updateCollaboratorActionService->disable($folder->id, Feature::UPDATE_FOLDER_DESCRIPTION);

        $this->loginUser($collaborator);
        $this->updateFolderResponse(['name' => 'baz', 'folder_id' => $folder->public_id->present()])->assertOk();

        $this->updateFolderResponse(['description' => $this->faker->word, 'folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment($errorMessage = ['message' => 'FolderFeatureDisAbled']);

        $this->updateFolderResponse(['description' => null, 'folder_id' => $folder->public_id->present()])->assertForbidden()->assertJsonFragment($errorMessage);

        $this->loginUser($folderOwner);
        $this->updateFolderResponse(['name' => 'bar', 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->updateFolderResponse(['description' => 'foo bar arena', 'folder_id' => $folder->public_id->present()])->assertOk();
    }

    #[Test]
    public function whenUpdateIconFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo', 'description' => 'foo bar folder']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        $updateCollaboratorActionService->disable($folder->id, Feature::UPDATE_FOLDER_ICON);

        $this->loginUser($collaborator);
        $this->updateFolderResponse(['name' => 'baz', 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->updateFolderResponse(['description' => 'foo baz', 'folder_id' => $folder->public_id->present()])->assertOk();

        $this->updateFolderResponse(['thumbnail' => UploadedFile::fake()->image('folderIcon.jpg')->size(2000), 'folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment($errorMessage = ['message' => 'FolderFeatureDisAbled']);

        $this->updateFolderResponse(['thumbnail' => null, 'folder_id' => $folder->public_id->present()])->assertForbidden()->assertJsonFragment($errorMessage);

        $this->loginUser($folderOwner);
        $this->updateFolderResponse(['thumbnail' => UploadedFile::fake()->image('folderIcon.jpg')->size(2000), 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->updateFolderResponse(['description' => 'barFoo', 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->updateFolderResponse(['name' => 'fooAndBar', 'folder_id' => $folder->public_id->present()])->assertOk();
    }
}
