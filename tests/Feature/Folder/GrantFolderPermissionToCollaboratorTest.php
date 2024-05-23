<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Enums\Permission;
use App\Repositories\Folder\CollaboratorPermissionsRepository as Repository;
use App\UAC;
use Closure;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\InteractsWithValues;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\GeneratesId;

class GrantFolderPermissionToCollaboratorTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use InteractsWithValues;
    use GeneratesId;

    protected function grantPermissionsResponse(array $parameters = []): TestResponse
    {
        $routeParameters = Arr::except($parameters, 'permissions');

        return $this->patchJson(
            route('grantPermission', $routeParameters),
            Arr::only($parameters, ['permissions'])
        );
    }

    protected function shouldBeInteractedWith(): mixed
    {
        return UAC::validExternalIdentifiers();
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}/permissions', 'grantPermission');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->grantPermissionsResponse(['collaborator_id' => 4, 'folder_id' => 4])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenRouteParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->make(['id' => 232]));

        $this->grantPermissionsResponse([
            'folder_id' => $this->generateFolderId()->present(),
            'collaborator_id' => 'foo',
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertJsonFragment(['UserNotFound']);

        $this->grantPermissionsResponse([
            'folder_id' => 'foo',
            'collaborator_id' => $this->generateUserId()->present(),
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertJsonFragment(['FolderNotFound']);
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->grantPermissionsResponse($query = ['folder_id' => $this->generateFolderId()->present(), $this->generateUserId()->present()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions' => ['The permissions field is required.']
            ]);

        $this->grantPermissionsResponse(['permissions' => 'foo,bar', ...$query])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => ['The selected permissions is invalid.']]);

        $this->grantPermissionsResponse(['permissions' => 'addBookmarks,addBookmarks,inviteUsers', ...$query])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "permissions.0" => [
                    "The permissions.0 field has a duplicate value."
                ],
                "permissions.1" => [
                    "The permissions.1 field has a duplicate value."
                ]
            ]);
    }

    #[Test]
    #[DataProvider('grantPermissionsData')]
    public function grantPermissions(string|array $permissions, Closure $expectation = null): void
    {
        if (is_array($permissions)) {
            $permissions = implode(',', $permissions);
        }

        $expectation = $expectation ??= fn () => null;

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present(),
            'permissions'     => $permissions
        ])->assertOk();

        $collaboratorNewPermissions = (new Repository())->all($collaborator->id, $folder->id);

        $this->assertEqualsCanonicalizing(
            explode(',', $permissions),
            $collaboratorNewPermissions->toExternalIdentifiers()
        );

        if ($result = $expectation($collaboratorNewPermissions)) {
            $this->assertTrue($result);
        }
    }

    public static function grantPermissionsData(): array
    {
        return  [
            'All'                       => [UAC::all()->toExternalIdentifiers()],
            'Add bookmarks'             => ['addBookmarks'],
            'Remove bookmarks'          => ['removeBookmarks'],
            'Invite users'              => ['inviteUsers'],
            'Update folder name'        => ['updateFolderName'],
            'Update folder description' => ['updateFolderDescription'],
            'Update folder Icon'        => ['updateFolderIcon'],
            'Add and Remove bookmarks'  => ['addBookmarks,removeBookmarks']
        ];
    }

    public function testWillReturnForbiddenWhenGrantingPermissionToSelf(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->loginUser($user);
        $this->grantPermissionsResponse([
            'collaborator_id' => $user->public_id->present(),
            'folder_id' => $folder->public_id->present(),
            'permissions' => 'inviteUsers'
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotGrantPermissionsToSelf']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->grantPermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->public_id->present(),
            'folder_id'   => FolderFactory::new()->create()->public_id->present(),
            'permissions' => 'inviteUsers'
        ])->assertNotFound();
    }

    public function testWillReturnConflictWhenCollaboratorAlreadyHasPermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'   => $folder->public_id->present(),
            'permissions' => 'addBookmarks'
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson(['message' => 'DuplicatePermissions']);
    }

    public function testWillReturnNotFoundWhenUserIsNotACollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'   => $folder->public_id->present(),
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotExist(): void
    {
        $folderOwner = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $this->generateUserId()->present(),
            'folder_id'   => $folder->public_id->present(),
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $this->loginUser($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'       => $this->generateFolderId()->present(),
            'permissions'     => 'addBookmarks'
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}
