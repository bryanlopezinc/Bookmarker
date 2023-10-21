<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Models\BannedCollaborator;
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

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteCollaboratorResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteCollaboratorResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id' => ['The user id field is required'],
                'folder_id' => ['The folder id field is required']
            ]);

        $this->deleteCollaboratorResponse([
            'user_id' => 'foo',
            'folder_id' => 'bar'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'folder_id']);

        $this->deleteCollaboratorResponse(['ban' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ban']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'user_id'   => UserFactory::new()->create()->id,
            'folder_id' => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteCollaboratorResponse([
            'user_id'   => UserFactory::new()->create()->id,
            'folder_id' => FolderFactory::new()->create()->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenUserIsNotACollaborator(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'user_id'   => UserFactory::new()->create()->id,
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'user_id'   => UserFactory::new()->create()->id + 1,
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnForbiddenWhenUserIsRemovingSelf(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        Passport::actingAs($user);
        $this->deleteCollaboratorResponse([
            'user_id'   => $user->id,
            'folder_id' => $folder->id
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotRemoveSelf']);
    }

    public function testRemoveCollaborator(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($user)->create();
        $folderCollaboratorPermissionFactory = FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id);

        $folderCollaboratorPermissionFactory->viewBookmarksPermission()->create();
        $folderCollaboratorPermissionFactory->addBookmarksPermission()->create();
        $folderCollaboratorPermissionFactory->removeBookmarksPermission()->create();

        Passport::actingAs($user);
        $this->deleteCollaboratorResponse([
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id
        ])->assertOk();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id
        ]);

        $this->assertDatabaseMissing(BannedCollaborator::class, [
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id
        ]);
    }

    public function testRemoveAndBanCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($folderOwner);
        $this->deleteCollaboratorResponse([
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id,
            'ban'       => true
        ])->assertOk();

        $this->assertDatabaseHas(BannedCollaborator::class, [
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id
        ]);
    }

    public function testWillNotRemoveOtherCollaborators(): void
    {
        $users = UserFactory::times(3)->create();
        $folder = FolderFactory::new()->for($users[0])->create();

        FolderCollaboratorPermissionFactory::new()->user($users[1]->id)->folder($folder->id)->create();
        FolderCollaboratorPermissionFactory::new()->user($users[2]->id)->folder($folder->id)->create();

        Passport::actingAs($users[0]);
        $this->deleteCollaboratorResponse([
            'user_id'   => $users[1]->id,
            'folder_id' => $folder->id
        ])->assertOk();

        $this->assertDatabaseHas(FolderCollaboratorPermission::class, [
            'user_id'   => $users[2]->id,
            'folder_id' => $folder->id
        ]);
    }
}
