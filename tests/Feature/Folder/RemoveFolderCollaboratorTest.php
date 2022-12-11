<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Models\FolderCollaboratorPermission;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RemoveFolderCollaboratorTest extends TestCase
{
    use WithFaker;

    protected function deleteCollaboratorResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolderCollaborator', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/collaborators', 'deleteFolderCollaborator');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->deleteCollaboratorResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteCollaboratorResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id' => ['The user id field is required'],
                'folder_id' => ['The folder id field is required']
            ]);
    }

    public function testAttributesMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteCollaboratorResponse([
            'user_id' => 'foo',
            'folder_id' => 'bar'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'folder_id']);
    }

    public function testFolderMustExist(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'user_id' => UserFactory::new()->create()->id,
            'folder_id' => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'The folder does not exists'
            ]);
    }

    public function testFolderMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteCollaboratorResponse([
            'user_id' => UserFactory::new()->create()->id,
            'folder_id' => FolderFactory::new()->create()->id
        ])->assertForbidden();
    }

    public function testUserMustBeAPresentCollaborator(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'user_id' => UserFactory::new()->create()->id,
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'User not a collaborator'
            ]);
    }

    public function testWhenUser_id_DoesNotBelongToARegisteredUser(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'user_id' => UserFactory::new()->create()->id + 1,
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'User not a collaborator'
            ]);
    }

    public function testCannotRemoveSelf(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($user);
        $this->deleteCollaboratorResponse([
            'user_id' => $user->id,
            'folder_id' => $folder->id
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot remove self'
            ]);
    }

    public function testWillRemoveCollaborator(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($user)->create();
        $folderCollaboratorPermissionFactory = FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id);

        $folderCollaboratorPermissionFactory->viewBookmarksPermission()->create();
        $folderCollaboratorPermissionFactory->addBookmarksPermission()->create();
        $folderCollaboratorPermissionFactory->removeBookmarksPermission()->create();

        Passport::actingAs($collaborator);
        $this->getJson(route('folderBookmarks', ['folder_id' => $folder->id]))->assertOk();

        Passport::actingAs($user);
        $this->deleteCollaboratorResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id
        ])->assertOk();

        //collaborator can no longer access folder.
        Passport::actingAs($collaborator);
        $this->getJson(route('folderBookmarks', ['folder_id' => $folder->id]))->assertForbidden();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id
        ]);
    }

    public function testWillNotRemoveOtherCollaborators(): void
    {
        [$user, $collaborator, $anotherCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($user)->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();
        FolderCollaboratorPermissionFactory::new()->user($anotherCollaborator->id)->folder($folder->id)->create();

        Passport::actingAs($user);
        $this->deleteCollaboratorResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id
        ])->assertOk();

        $this->assertDatabaseHas(FolderCollaboratorPermission::class, [
            'user_id' => $anotherCollaborator->id,
            'folder_id' => $folder->id
        ]);
    }
}
