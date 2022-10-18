<?php

namespace Tests\Feature\Folder;

use App\Models\FolderAccess;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderAccessFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class LeaveFolderCollaborationTest extends TestCase
{
    use WithFaker;

    protected function leaveFolderCollaborationResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('leaveFolderCollaboration'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/collaborations/exit', 'leaveFolderCollaboration');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->leaveFolderCollaborationResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id']);
    }

    public function testWillThrowValidationWhenAttributesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse(['folder_id' => '2bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "folder_id" => ["The folder_id attribute is invalid"],
            ]);
    }

    public function testExitCollaboration(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($collaborator);

        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        $this->assertDatabaseMissing(FolderAccess::class, [
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id
        ]);
    }

    public function testWillOnlyLeaveSpecifiedFolder(): void
    {
        [$mark, $tony, $collaborator] = UserFactory::new()->count(3)->create();
        $marksFolder = FolderFactory::new()->create(['user_id' => $mark->id]);
        $tonysFolder = FolderFactory::new()->create(['user_id' => $tony->id]);

        FolderAccessFactory::new()->user($collaborator->id)->folder($marksFolder->id)->create();
        FolderAccessFactory::new()->user($collaborator->id)->folder($tonysFolder->id)->create();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse([
            'folder_id' => $marksFolder->id
        ])->assertOk();

        $this->assertDatabaseMissing(FolderAccess::class, [
            'user_id' => $collaborator->id,
            'folder_id' => $marksFolder->id
        ]);

        $this->assertDatabaseHas(FolderAccess::class, [
            'user_id' => $collaborator->id,
            'folder_id' => $tonysFolder->id
        ]);
    }

    public function testWhenUserIsNotACollaborator(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->leaveFolderCollaborationResponse([
            'folder_id' =>  FolderFactory::new()->create()->id
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'User not a collaborator'
            ]);
    }

    public function testWhenFolderDoesNotExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->leaveFolderCollaborationResponse([
            'folder_id' =>  FolderFactory::new()->create()->id + 1
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'The folder does not exists'
            ]);
    }

    public function testCannotExitFromOwnFolder(): void
    {
        Passport::actingAs($folderOwner = UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse([
            'folder_id' =>  FolderFactory::new()->create(['user_id' => $folderOwner->id])->id
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot exit from own folder'
            ]);
    }

    public function testWillNotHaveAccessToFolderAfterAction(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);
        $factory = FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id);
        $collaboratorBookmarks = BookmarkFactory::new()->count(2)->create();

        $factory->addBookmarksPermission()->create();
        $factory->inviteUser()->create();
        $factory->removeBookmarksPermission()->create();

        Passport::actingAs($collaborator);

        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $collaboratorBookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertForbidden();

        $this->deleteJson(route('removeBookmarksFromFolder'), [
            'bookmarks' => $collaboratorBookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertForbidden();

        $this->getJson(route('sendFolderCollaborationInvite', [
            'email' => UserFactory::new()->create()->email,
            'folder_id' => $folder->id,
        ]))->assertForbidden();

        $this->getJson(route('folderBookmarks', [
            'folder_id' => $folder->id
        ]))->assertForbidden();
    }

    public function testFolderWillNotShowInUserCollaborations(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($collaborator);

        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        $this->getJson(route('fetchUserCollaborations'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testFolderOwnerWillNotSeeUserAsCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($folderOwner);
        $this->getJson(route('fetchFolderCollaborators', [
            'folder_id' => $folder->id
        ]))->assertOk()
            ->assertJsonCount(1, 'data');

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        Passport::actingAs($folderOwner);
        $this->getJson(route('fetchFolderCollaborators', [
            'folder_id' => $folder->id
        ]))->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
