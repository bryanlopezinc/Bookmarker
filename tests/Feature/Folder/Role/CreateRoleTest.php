<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Role;

use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\InteractsWithValues;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;

class CreateRoleTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use InteractsWithValues;

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

        $this->createRoleResponse(['folder_id' => 'baz'])->assertNotFound();

        $this->createRoleResponse(['folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name field is required.']);

        $this->createRoleResponse(['name' => str_repeat('F', 65), 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 64 characters.']);

        $this->createRoleResponse(['name' => ' ', 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->createRoleResponse(['permissions' => ['addBookmarks', 'addBookmarks'], 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0' => 'The permissions.0 field has a duplicate value.']);

        $this->createRoleResponse(['permissions' => ['*'], 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'The selected permissions is invalid.']);

        $this->createRoleResponse(['permissions' => [], 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);

        $this->createRoleResponse(['permissions' => ['addBookmarks', 'foo'], 'folder_id' => 4])
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
            'folder_id'   => $folder->id
        ])->assertCreated();

        $this->assertEquals($name, $folder->refresh()->roles->sole()->name);
        $this->assertEqualsCanonicalizing(
            $folder->roles->sole()->permissions->pluck('name')->all(),
            UAC::fromRequest($permissions)->toArray()
        );
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
            'folder_id'   => $otherUserFolder->id
        ])->assertCreated();

        $this->loginUser($user);
        $this->createRoleResponse([
            'permissions' => ['addBookmarks', 'removeBookmarks'],
            'name'        => $roleNameSharedByBothUsers,
            'folder_id'   => $userFolder->id
        ])->assertCreated();

        $this->createRoleResponse([
            'permissions' => ['updateFolder', 'removeBookmarks'],
            'name'        => $roleNameSharedByBothUsers,
            'folder_id'   => $userFolder->id
        ])->assertConflict()->assertJsonFragment(['message' => 'DuplicateRoleName']);

        $this->createRoleResponse([
            'permissions' => ['updateFolder', 'removeBookmarks'],
            'name'        => $roleNameSharedByBothUsers,
            'folder_id'   => $userSecondFolder->id
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
            'folder_id'   => $otherUserFolder->id
        ])->assertCreated();

        $this->loginUser($user);
        $this->createRoleResponse($query = [
            'permissions' => $rolePermissions,
            'name'        => $name = $this->faker->word,
            'folder_id'   => $userFolder->id
        ])->assertCreated();

        $this->createRoleResponse(array_replace($query, ['name' => $this->faker->word]))
            ->assertConflict()
            ->assertJsonFragment(['message' => 'DuplicateRole'])
            ->assertJsonFragment(['info' => "A role with name {$name} already contains exact same permissions"]);

        $this->createRoleResponse(array_replace($query, ['folder_id' => $userSecondFolder->id]))->assertCreated();

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
            'folder_id'   => $folder->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser($collaborator);
        $this->createRoleResponse($query)->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertCount(0, $folder->roles);
    }

    #[Test]
    public function whenFolderDoesNotExists(): void
    {
        $folder = FolderFactory::new()->create();

        $this->loginUser(UserFactory::new()->create());
        $this->createRoleResponse([
            'permissions' => ['addBookmarks', 'removeBookmarks'],
            'name'        => $this->faker->word,
            'folder_id'   => $folder->id + 1
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}
