<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Cache\InviteTokensStore;
use App\UAC;
use App\Models\FolderAccess;
use App\Models\FolderPermission;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderAccessFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\ValueObjects\Uuid;
use Laravel\Passport\Database\Factories\ClientFactory;

class RevokeCollaboratorPermissionsTest extends TestCase
{
    private function revokePermissionsResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('revokePermissions'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/collaborators/revoke_permissions', 'revokePermissions');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->revokePermissionsResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->revokePermissionsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id' => ['The user id field is required'],
                'folder_id' => ['The folder id field is required'],
                'permissions' => ['The permissions field is required']
            ]);
    }

    public function testAttributesMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->revokePermissionsResponse([
            'user_id' => 'foo',
            'folder_id' => 'bar',
            'permissions' => 'hello'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id',
                'folder_id',
                'permissions' => ['The selected permissions is invalid.']
            ]);
    }

    public function testPermissionsAttributeMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->revokePermissionsResponse([
            'permissions' => 'addBookmarks,addBookmarks'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                "permissions.0" => ["The permissions.0 field has a duplicate value."],
                "permissions.1" => ["The permissions.1 field has a duplicate value."]
            ]);
    }

    public function testFolderMustExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->revokePermissionsResponse([
            'user_id' => UserFactory::new()->create()->id,
            'folder_id' =>  FolderFactory::new()->create()->id + 1,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'The folder does not exists'
            ]);
    }

    public function testFolderMustBelongToUser(): void
    {
        [$folderOwner, $anotherUser, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Passport::actingAs($anotherUser);

        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertForbidden();
    }

    public function testCollaboratorCannotRevokeAccess(): void
    {
        [$folderOwner, $collaborator, $anotherCollaborator] = UserFactory::times(3)->create();

        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;

        FolderAccessFactory::new()->user($collaborator->id)->folder($folderID)->create();
        FolderAccessFactory::new()->user($anotherCollaborator->id)->folder($folderID)->addBookmarksPermission()->create();

        Passport::actingAs($collaborator);

        $this->revokePermissionsResponse([
            'user_id' => $anotherCollaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertForbidden();

        $this->revokePermissionsResponse([
            'user_id' => $folderOwner->id,
            'folder_id' => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertForbidden();

        $this->assertDatabaseHas(FolderAccess::class, [
            'folder_id' => $folderID,
            'user_id' => $anotherCollaborator->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::ADD_BOOKMARKS)->sole()->id
        ]);
    }

    public function testUserMustBeAPresentCollaborator(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->revokePermissionsResponse([
            'user_id' => UserFactory::new()->create()->id,
            'folder_id' => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'User not a collaborator'
            ]);
    }

    public function testWhenUser_id_DoesNotBelongToARegisteredUser(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->revokePermissionsResponse([
            'user_id' => UserFactory::new()->create()->id + 1,
            'folder_id' => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'User not a collaborator'
            ]);
    }

    public function testCannotPerformActionOnSelf(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        Passport::actingAs($user);

        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        $this->revokePermissionsResponse([
            'user_id' => $user->id,
            'folder_id' => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot perform action on self'
            ]);
    }

    public function testCollaboratorMustAlreadyHavePermissions(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        Passport::actingAs($user);
        FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id)->addBookmarksPermission()->create();

        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'permissions' => 'removeBookmarks'
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'User does not have such permissions'
            ]);
    }

    public function testWillRevokeAddBookmarksPermission(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        $folderID = FolderFactory::new()->create(['user_id' => $user->id])->id;
        $collaboratorBookmarks = BookmarkFactory::new()->count(3)->create(['user_id' => $collaborator->id])->pluck('id');

        FolderAccessFactory::new()->user($collaborator->id)->folder($folderID)->addBookmarksPermission()->create();

        //collaborator can add bookmarks
        Passport::actingAs($collaborator);
        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $collaboratorBookmarks->implode(','),
            'folder' => $folderID,
        ])->assertCreated();

        //folder owner revokes addBookmarks permission
        Passport::actingAs($user);
        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertOk();

        //collaborator can no longer add bookmarks
        Passport::actingAs($collaborator);
        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $collaboratorBookmarks->implode(','),
            'folder' => $folderID
        ])->assertForbidden();

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folderID,
            'user_id' => $collaborator->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::ADD_BOOKMARKS)->sole()->id
        ]);
    }

    public function testWillRevokeRemoveBookmarksPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;

        FolderAccessFactory::new()->user($collaborator->id)->folder($folderID)->removeBookmarksPermission()->create();

        //collaborator can remove bookmarks
        Passport::actingAs($collaborator);
        $this->deleteJson(route('removeBookmarksFromFolder'), [
            'bookmarks' => '2,4,5',
            'folder' => $folderID,
        ])->assertNotFound(); // Not found means collaborator has access but bookmarks don't exist

        //folder owner removes permission
        Passport::actingAs($folderOwner);
        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'removeBookmarks'
        ])->assertOk();

        //collaborator can no longer remove bookmarks
        Passport::actingAs($collaborator);
        $this->deleteJson(route('removeBookmarksFromFolder'), [
            'bookmarks' => '2,4,5',
            'folder' => $folderID,
        ])->assertForbidden();

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folderID,
            'user_id' => $collaborator->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::DELETE_BOOKMARKS)->sole()->id
        ]);
    }

    public function testWillRevokeInviteUserPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;

        FolderAccessFactory::new()->user($collaborator->id)->folder($folderID)->inviteUser()->create();

        //collaborator can invite user
        Passport::actingAs($collaborator);
        $this->getJson(route('sendFolderCollaborationInvite', [
            'email' => UserFactory::new()->create()->email,
            'folder_id' => $folderID,
        ]))->assertOk();

        //folder owner revokes access
        Passport::actingAs($folderOwner);
        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'inviteUser'
        ])->assertOk();

        //collaborator cannot invite user
        Passport::actingAs($collaborator);
        $this->getJson(route('sendFolderCollaborationInvite', [
            'email' => UserFactory::new()->create()->email,
            'folder_id' => $folderID,
        ]))->assertForbidden();

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folderID,
            'user_id' => $collaborator->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::INVITE)->sole()->id
        ]);
    }

    public function testCanRevokeMultiplePermissionsInOneRequest(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;

        FolderAccessFactory::new()->user($collaborator->id)->folder($folderID)->inviteUser()->create();
        FolderAccessFactory::new()->user($collaborator->id)->folder($folderID)->addBookmarksPermission()->create();

        Passport::actingAs($folderOwner);
        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'inviteUser,addBookmarks'
        ])->assertOk();

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folderID,
            'user_id' => $collaborator->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::ADD_BOOKMARKS)->sole()->id
        ]);

        $this->assertDatabaseMissing(FolderAccess::class, [
            'folder_id' => $folderID,
            'user_id' => $collaborator->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::INVITE)->sole()->id
        ]);
    }

    public function testWillNotRevokeCollaboratorsOtherPermissions(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        $folderID = FolderFactory::new()->create(['user_id' => $user->id])->id;

        FolderAccessFactory::new()->user($collaborator->id)->folder($folderID)->addBookmarksPermission()->create();
        FolderAccessFactory::new()->user($collaborator->id)->folder($folderID)->inviteUser()->create();

        Passport::actingAs($user);
        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertOk();

        $this->assertDatabaseHas(FolderAccess::class, [
            'folder_id' => $folderID,
            'user_id' => $collaborator->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::INVITE)->sole()->id
        ]);
    }

    public function testWillNotAffectOtherCollaboratorsPermissions(): void
    {
        [$user, $collaborator, $anotherCollaborator] = UserFactory::times(3)->create();

        $folderID = FolderFactory::new()->create(['user_id' => $user->id])->id;

        FolderAccessFactory::new()->user($collaborator->id)->folder($folderID)->addBookmarksPermission()->create();
        FolderAccessFactory::new()->user($anotherCollaborator->id)->folder($folderID)->addBookmarksPermission()->create();

        Passport::actingAs($user);
        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertOk();

        $this->assertDatabaseHas(FolderAccess::class, [
            'folder_id' => $folderID,
            'user_id' => $anotherCollaborator->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::ADD_BOOKMARKS)->sole()->id
        ]);
    }

    public function testCollaboratorCanStillVewFolderBookmarksWhenPermissionsAreRevoked(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(3)->create();

        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;

        (new InviteTokensStore(app('cache')->store()))->store(
            $token = Uuid::generate(),
            new UserID($folderOwner->id),
            new UserID($collaborator->id),
            new ResourceID($folderID),
            UAC::fromUnSerialized([FolderPermission::ADD_BOOKMARKS])
        );

        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getJson(route('acceptFolderCollaborationInvite', [
            'invite_hash' => $token->value
        ]))->assertCreated();

        Passport::actingAs($folderOwner);
        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertOk();

        Passport::actingAs($collaborator);
        $this->getJson(route('folderBookmarks', ['folder_id' => $folderID]))->assertOk();
    }
}
