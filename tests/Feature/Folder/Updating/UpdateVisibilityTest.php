<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\Enums\FolderVisibility;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Illuminate\Support\Facades\Hash;
use Tests\Traits\GeneratesId;

class UpdateVisibilityTest extends TestCase
{
    use CreatesCollaboration;
    use GeneratesId;

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser();

        $this->updateFolderResponse(['visibility' => 'foo', 'folder_id' => $this->generateFolderId()->present()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['visibility' => 'The selected visibility is invalid.']);
    }

    #[Test]
    public function updateVisibilityToPublic(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $publicFolder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder_id'  => $publicFolder->public_id->present(),
        ])->assertNoContent();
        $this->assertUpdated($publicFolder, ['visibility' => FolderVisibility::PUBLIC->value]);

        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(['folder_id' => $passwordProtectedFolder->public_id->present(), 'visibility' => 'public'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The Password field is required for this action.']);

        $privateFolder = FolderFactory::new()->for($user)->private()->create();
        $this->updateFolderResponse(['folder_id' => $privateFolderId = $privateFolder->public_id->present(), 'visibility' => 'public'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The Password field is required for this action.']);

        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder_id'  => $privateFolderId,
            'password'   => 'I forgot my password please let me in'
        ])->assertUnauthorized()->assertJsonFragment(['message' => 'InvalidPasswordForFolderUpdate']);

        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder_id'  => $privateFolderId,
            'password'   => 'password'
        ])->assertOk();

        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder_id'  => $passwordProtectedFolder->public_id->present(),
            'password'   => 'password'
        ])->assertOk();

        $this->assertUpdated($privateFolder, ['visibility' => FolderVisibility::PUBLIC->value]);
        $this->assertUpdated($passwordProtectedFolder, ['visibility' => FolderVisibility::PUBLIC->value, 'password' => null]);
    }

    #[Test]
    public function updateVisibilityToPrivate(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $privateFolder = FolderFactory::new()->for($user)->private()->create();
        $this->updateFolderResponse([
            'visibility' => 'private',
            'folder_id'  => $privateFolder->public_id->present(),
        ])->assertNoContent();
        $this->assertUpdated($privateFolder, ['visibility' => FolderVisibility::PRIVATE->value]);

        $publicFolder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse([
            'visibility' => 'private',
            'folder_id'  => $publicFolder->public_id->present(),
        ])->assertOk();
        $this->assertUpdated($publicFolder, ['visibility' => FolderVisibility::PRIVATE->value]);

        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(['folder_id' => $passwordProtectedFolder->public_id->present(), 'visibility' => 'private'])
            ->assertOk();
        $this->assertUpdated($passwordProtectedFolder, ['visibility' => FolderVisibility::PRIVATE->value, 'password' => null]);

        $folderThatHasCollaborators = FolderFactory::new()->for($user)->create();
        $this->CreateCollaborationRecord(UserFactory::new()->create(), $folderThatHasCollaborators);
        $this->updateFolderResponse(['visibility' => 'private', 'folder_id' => $folderThatHasCollaborators->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'CannotMakeFolderWithCollaboratorsPrivate']);
    }

    #[Test]
    public function updateVisibilityToCollaboratorsOnly(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folderVisibleToCollaboratorsOnly = FolderFactory::new()->for($user)->visibleToCollaboratorsOnly()->create();
        $this->updateFolderResponse([
            'visibility' => 'collaborators',
            'folder_id'  => $folderVisibleToCollaboratorsOnly->public_id->present(),
        ])->assertNoContent();
        $this->assertUpdated($folderVisibleToCollaboratorsOnly, ['visibility' => FolderVisibility::COLLABORATORS->value]);

        $publicFolder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse([
            'visibility' => 'collaborators',
            'folder_id'  => $publicFolder->public_id->present(),
        ])->assertOk();
        $this->assertUpdated($publicFolder, ['visibility' => FolderVisibility::COLLABORATORS->value]);

        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(['folder_id' => $passwordProtectedFolder->public_id->present(), 'visibility' => 'collaborators'])
            ->assertOk();
        $this->assertUpdated($passwordProtectedFolder, ['visibility' => FolderVisibility::COLLABORATORS->value, 'password' => null]);
    }

    #[Test]
    public function updateVisibilityToPasswordProtected(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->updateFolderResponse([
            'folder_id' => FolderFactory::new()->for($user)->create()->public_id->present(),
            'visibility' => 'password_protected',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_password' => 'The folder password field is required.']);

        $query = ['visibility' => 'password_protected', 'folder_password' => 'password'];

        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(['folder_id' => $passwordProtectedFolder->public_id->present(), ...$query])->assertOk();
        $passwordProtectedFolder->refresh();
        $this->assertEquals($passwordProtectedFolder->visibility, FolderVisibility::PASSWORD_PROTECTED);
        $this->assertTrue(Hash::check('password', $passwordProtectedFolder->password));

        $publicFolder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse(['folder_id' => $publicFolder->public_id->present(), ...$query])->assertOk();
        $publicFolder->refresh();
        $this->assertEquals($publicFolder->visibility, FolderVisibility::PASSWORD_PROTECTED);
        $this->assertTrue(Hash::check('password', $publicFolder->password));

        $folderThatHasCollaborators = FolderFactory::new()->for($user)->create();
        $this->CreateCollaborationRecord(UserFactory::new()->create(), $folderThatHasCollaborators);
        $this->updateFolderResponse(['folder_id' => $folderThatHasCollaborators->public_id->present(), ...$query])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'CannotMakeFolderWithCollaboratorsPrivate']);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsUpdatingVisibility(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folderVisibleToCollaboratorsOnly = FolderFactory::new()->for($folderOwner)->visibleToCollaboratorsOnly()->create();
        $publicFolder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folderVisibleToCollaboratorsOnly, Permission::updateFolderTypes());
        $this->CreateCollaborationRecord($collaborator, $publicFolder, Permission::updateFolderTypes());

        $this->loginUser($collaborator);

        $this->updateFolderResponse($query = [
            'folder_id'   => $folderVisibleToCollaboratorsOnly->public_id->present(),
            'visibility'  => 'public',
            'password'    => 'password'
        ])->assertForbidden()->assertJsonFragment($error = ['message' => 'CannotUpdateFolderAttribute']);

        $this->updateFolderResponse(array_replace($query, ['visibility' => 'password_protected', 'folder_password' => 'password']))->assertForbidden()->assertJsonFragment($error);
        $this->updateFolderResponse(array_replace($query, ['visibility' => 'private']))->assertForbidden()->assertJsonFragment($error);
        $this->updateFolderResponse(array_replace($query, ['visibility' => 'collaborators']))->assertForbidden()->assertJsonFragment($error);

        $this->updateFolderResponse($query = [
            'folder_id' => $publicFolder->public_id->present(),
            'visibility'  => 'private'
        ])->assertForbidden()->assertJsonFragment($error);

        $this->updateFolderResponse(array_replace($query, ['visibility' => 'password_protected', 'folder_password' => 'password']))->assertForbidden()->assertJsonFragment($error);
        $this->updateFolderResponse(array_replace($query, ['visibility' => 'private']))->assertForbidden()->assertJsonFragment($error);
        $this->updateFolderResponse(array_replace($query, ['visibility' => 'collaborators']))->assertForbidden()->assertJsonFragment($error);
        $this->updateFolderResponse(array_replace($query, ['visibility' => 'public']))->assertForbidden()->assertJsonFragment($error);

        $this->assertEquals($folderVisibleToCollaboratorsOnly, $folderVisibleToCollaboratorsOnly->refresh());
        $this->assertEquals($publicFolder, $publicFolder->refresh());
    }
}
