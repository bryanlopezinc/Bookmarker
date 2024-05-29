<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Role;

use App\Models\FolderRole;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\InteractsWithValues;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\GeneratesId;

class CreateRoleTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use InteractsWithValues;
    use GeneratesId;

    protected function shouldBeInteractedWith(): mixed
    {
        return UAC::validExternalIdentifiers();
    }

    protected function createRoleResponse(array $parameters = []): TestResponse
    {
        if (array_key_exists('permissions', $parameters)) {
            $parameters['permissions'] = implode(',', $parameters['permissions']);
        }

        return $this->postJson(
            route('createFolderRole', Arr::only($parameters, $routeParameters = ['folder_id'])),
            Arr::except($parameters, $routeParameters)
        );
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/roles', 'createFolderRole');
    }

    #[Test]
    public function unAuthorizedUserCannotAccessRoute(): void
    {
        $this->createRoleResponse(['folder_id' => 5])->assertUnauthorized();
    }

    #[Test]
    public function whenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->make(['id' => 55]));

        $this->createRoleResponse(['folder_id' => 'baz', 'name' => 'foo', 'permissions' => ['addBookmarks']])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->createRoleResponse(['folder_id' => $id = $this->generateFolderId()->present()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name field is required.']);

        $this->createRoleResponse(['name' => str_repeat('F', 65), 'folder_id' => $id, 'permissions' => ['addBookmarks']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 64 characters.']);

        $this->createRoleResponse(['name' => ' ', 'folder_id' => $id, 'permissions' => ['addBookmarks']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->createRoleResponse(['permissions' => ['addBookmarks', 'addBookmarks'], 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0' => 'The permissions.0 field has a duplicate value.']);

        $this->createRoleResponse(['permissions' => ['*'], 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'The selected permissions is invalid.']);

        $this->createRoleResponse(['permissions' => [], 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);

        $this->createRoleResponse(['permissions' => ['addBookmarks', 'foo'], 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'The selected permissions is invalid.']);
    }

    #[Test]
    public function createRole(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createRoleResponse([
            'permissions' => $permissions = ['addBookmarks', 'inviteUsers', 'removeUser'],
            'name'        => $name = $this->faker->word,
            'folder_id'   => $folder->public_id->present()
        ])->assertCreated();

        /** @var FolderRole */
        $role = $folder->refresh()->roles->sole();

        $this->assertEquals($name, $role->name);
        $this->assertNotEquals($role->public_id->value, $role->public_id->present());
        $this->assertEqualsCanonicalizing(
            $folder->roles->sole()->permissions->pluck('name')->all(),
            UAC::fromRequest($permissions)->toArray()
        );
    }

    #[Test]
    #[DataProvider('createRoleWithPermissionsData')]
    public function createRoleWithPermissions(array $permissions): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createRoleResponse([
            'permissions' => $permissions,
            'name'        => $this->faker->word,
            'folder_id'   => $folder->public_id->present()
        ])->assertCreated();

        $this->assertEqualsCanonicalizing(
            $folder->roles->sole()->permissions->pluck('name')->all(),
            UAC::fromRequest($permissions)->toArray()
        );
    }

    public static function createRoleWithPermissionsData(): array
    {
        return  [
            'Add bookmarks'             => [['addBookmarks']],
            'Remove bookmarks'          => [['removeBookmarks']],
            'Invite users'              => [['inviteUsers']],
            'Remove Collaborator'       => [['removeUser']],
            'Update folder name'        => [['updateFolderName']],
            'Update folder description' => [['updateFolderDescription']],
            'Update folder Icon'        => [['updateFolderIcon']],
            'Suspend User'              => [['suspendUser']],
            'blacklist domain'          => [['blacklistDomain']],
            'whitelist domain'          => [['whitelistDomain']],
        ];
    }

    #[Test]
    public function roleNameMustBeUniqueToFolder(): void
    {
        [$user, $otherUser] = UserFactory::times(2)->create();

        [$userFolder, $userSecondFolder] = FolderFactory::times(2)->for($user)->create();

        $otherUserFolder = FolderFactory::new()->for($otherUser)->create();

        $this->loginUser($otherUser);
        $this->createRoleResponse([
            'permissions' => ['addBookmarks', 'removeBookmarks'],
            'name'        => $roleNameSharedByBothUsers = $this->faker->word,
            'folder_id'   => $otherUserFolder->public_id->present()
        ])->assertCreated();

        $this->loginUser($user);
        $this->createRoleResponse([
            'permissions' => ['addBookmarks', 'removeBookmarks'],
            'name'        => $roleNameSharedByBothUsers,
            'folder_id'   => $userFolder->public_id->present()
        ])->assertCreated();

        $this->createRoleResponse([
            'permissions' => ['updateFolderName', 'removeBookmarks'],
            'name'        => $roleNameSharedByBothUsers,
            'folder_id'   => $userFolder->public_id->present()
        ])->assertConflict()->assertJsonFragment(['message' => 'DuplicateRoleName']);

        $this->createRoleResponse([
            'permissions' => ['updateFolderName', 'removeBookmarks'],
            'name'        => $roleNameSharedByBothUsers,
            'folder_id'   => $userSecondFolder->public_id->present()
        ])->assertCreated();

        $this->assertCount(1, $userFolder->roles);
    }

    #[Test]
    public function willReturnConflictWhenARoleWithSamePermissionsExistsForSameFolder(): void
    {
        [$user, $otherUser] = UserFactory::times(2)->create();

        [$userFolder, $userSecondFolder] = FolderFactory::times(2)->for($user)->create();

        $otherUserFolder = FolderFactory::new()->for($otherUser)->create();

        $this->loginUser($otherUser);
        $this->createRoleResponse([
            'permissions' => $rolePermissions = ['addBookmarks', 'removeBookmarks'],
            'name'        => $this->faker->word,
            'folder_id'   => $otherUserFolder->public_id->present()
        ])->assertCreated();

        $query = collect([
            'permissions' => $rolePermissions,
            'name'        => $name = $this->faker->word,
            'folder_id'   => $userFolder->public_id->present()
        ]);

        $this->loginUser($user);
        $this->createRoleResponse($query ->all())->assertCreated();

        $this->createRoleResponse($query->replace(['name' => $this->faker->word])->all())
            ->assertConflict()
            ->assertJsonFragment(['message' => 'DuplicateRole'])
            ->assertJsonFragment(['info' => "A role with name {$name} already contains exact same permissions"]);

        $this->createRoleResponse($query->replace(['folder_id' => $userSecondFolder->public_id->present()])->all())->assertCreated();

        $this->assertCount(1, $userFolder->roles);
    }

    #[Test]
    public function whenFolderDoesNotBelongToUser(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($user);
        $this->createRoleResponse($query = [
            'permissions' => ['addBookmarks', 'removeBookmarks'],
            'name'        => $this->faker->word,
            'folder_id'   => $folder->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser($collaborator);
        $this->createRoleResponse($query)->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertCount(0, $folder->roles);
    }

    #[Test]
    public function whenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());
        $this->createRoleResponse([
            'permissions' => ['addBookmarks', 'removeBookmarks'],
            'name'        => $this->faker->word,
            'folder_id'   => $this->generateFolderId()->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}
