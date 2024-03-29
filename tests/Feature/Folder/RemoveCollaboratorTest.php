<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Models\BannedCollaborator;
use App\Models\FolderCollaborator;
use App\Models\FolderCollaboratorPermission;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Repositories\Folder\CollaboratorRepository;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RemoveCollaboratorTest extends TestCase
{
    use WithFaker;

    protected function deleteCollaboratorResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolderCollaborator', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}', 'deleteFolderCollaborator');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteCollaboratorResponse(['folder_id' => 44, 'collaborator_id' => 33])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenRouteParametersAreInvalid(): void
    {
        $this->deleteCollaboratorResponse(['folder_id' => 44, 'collaborator_id' => 'foo'])->assertNotFound();
        $this->deleteCollaboratorResponse(['folder_id' => 'foo', 'collaborator_id' => 44])->assertNotFound();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteCollaboratorResponse(['ban' => 'foo', 'folder_id' => 44, 'collaborator_id' => 33])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ban']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id' => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id' => FolderFactory::new()->create()->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenUserIsNotACollaborator(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->id + 1,
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
            'collaborator_id' => $user->id,
            'folder_id' => $folder->id
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotRemoveSelf']);
    }

    public function testRemoveCollaborator(): void
    {
        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $collaboratorRepository = new CollaboratorRepository();
        $collaboratorPermissionsRepository = new CollaboratorPermissionsRepository();

        $collaboratorPermissionsRepository->create($collaborator->id, $folder->id, UAC::all());
        $collaboratorPermissionsRepository->create($otherCollaborator->id, $folder->id, UAC::all());

        $collaboratorRepository->create($folder->id, $collaborator->id, $folderOwner->id);
        $collaboratorRepository->create($folder->id, $otherCollaborator->id, $folderOwner->id);

        $this->loginUser($folderOwner);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id' => $folder->id
        ])->assertOk();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id
        ]);

        $this->assertDatabaseMissing(FolderCollaborator::class, [
            'collaborator_id' => $collaborator->id,
            'folder_id' => $folder->id
        ]);

        $this->assertDatabaseMissing(BannedCollaborator::class, [
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id
        ]);

        $this->assertDatabaseHas(FolderCollaboratorPermission::class, [
            'user_id'   => $otherCollaborator->id,
            'folder_id' => $folder->id
        ]);

        $this->assertDatabaseHas(FolderCollaborator::class, [
            'collaborator_id' => $otherCollaborator->id,
            'folder_id' => $folder->id
        ]);
    }

    public function testBanCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        (new CollaboratorRepository())->create($folder->id, $collaborator->id, $folderOwner->id);

        Passport::actingAs($folderOwner);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'ban'       => true
        ])->assertOk();

        $this->assertDatabaseHas(BannedCollaborator::class, [
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id
        ]);
    }
}
