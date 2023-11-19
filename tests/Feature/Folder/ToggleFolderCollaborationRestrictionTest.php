<?php

namespace Tests\Feature\Folder;

use App\Models\FolderDisabledAction;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ToggleFolderCollaborationRestrictionTest extends TestCase
{
    protected function updateFolderResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(
            route('updateFolderCollaboratorActions'),
            $parameters
        );
    }

    #[Test]
    public function url(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/collaborators/actions', 'updateFolderCollaboratorActions');
    }

    #[Test]
    public function willReturnAuthorizedWhenUserIsNotSignedIn(): void
    {
        $this->updateFolderResponse()->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->updateFolderResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id']);

        $this->updateFolderResponse(['addBookmarks' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['addBookmarks']);
    }

    #[Test]
    public function toggleAddBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $updateFolderResponse = function (array $query) use ($folder) {
            $query['folder_id'] = (string) $folder->id;
            return $this->updateFolderResponse($query);
        };

        $updateFolderResponse(['addBookmarks' => true])->assertOk();
        $this->assertDatabaseMissing(FolderDisabledAction::class, ['folder_id' => $folder->id]);

        $updateFolderResponse(['addBookmarks' => false])->assertOk();
        $this->assertDatabaseHas(FolderDisabledAction::class, $data = [
            'folder_id' => $folder->id,
            'action'    => 'ADD_BOOKMARKS',
        ]);

        $updateFolderResponse(['addBookmarks' => false])->assertOk();
        $this->assertDatabaseHas(FolderDisabledAction::class, $data);

        $updateFolderResponse(['addBookmarks' => true])->assertOk();
        $this->assertDatabaseMissing(FolderDisabledAction::class, ['folder_id' => $folder->id]);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folderId = FolderFactory::new()->create()->id;

        $this->updateFolderResponse(['folder_id' => $folderId, 'action' => 'addBookmarks'])->assertNotFound();

        $this->assertDatabaseMissing(FolderDisabledAction::class, ['folder_id' => $folderId]);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->updateFolderResponse(['folder_id' => $folder->id + 1, 'action' => 'addBookmarks'])->assertNotFound();

        $this->assertDatabaseMissing(FolderDisabledAction::class, ['folder_id' => $folder->id + 1]);
    }
}
