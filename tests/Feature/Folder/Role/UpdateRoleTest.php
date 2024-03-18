<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Role;

use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;

class UpdateRoleTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;

    protected function updateRoleResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(
            route('updateFolderRole', Arr::only($parameters, $routeParameters = ['folder_id', 'role_id'])),
            Arr::except($parameters, $routeParameters)
        );
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/roles/{role_id}', 'updateFolderRole');
    }

    #[Test]
    public function unAuthorizedUserCannotAccessRoute(): void
    {
        $this->updateRoleResponse(['folder_id' => 5, 'role_id' => 4])->assertUnauthorized();
    }

    #[Test]
    public function whenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->make(['id' => 55]));

        $routeParameters = ['folder_id' => $this->faker->randomDigitNotZero(), 'role_id' => $this->faker->randomDigitNotZero()];

        $this->updateRoleResponse(['folder_id' => 'baz', 'role_id' => 5])->assertNotFound();
        $this->updateRoleResponse(['folder_id' => 9, 'role_id' => 'foo'])->assertNotFound();

        $this->updateRoleResponse($routeParameters)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name field is required.']);

        $this->updateRoleResponse(['name' => str_repeat('F', 65), ...$routeParameters])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 64 characters.']);

        $this->updateRoleResponse(['name' => ' ', ...$routeParameters])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function updateRole(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $userSecondFolder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder);
        $this->createRole($anotherUserFolderRoleName = $this->faker->word);
        $this->createRole($userSecondFolderRoleName = $this->faker->word, $userSecondFolder);

        $parameters = collect([
            'name'      => $this->faker->word,
            'folder_id' => $folder->id,
            'role_id'   => $role->id
        ]);

        $this->updateRoleResponse($parameters->all())->assertOk();
        $this->assertEquals($parameters['name'], $role->refresh()->name);

        $this->updateRoleResponse($parameters->replace(['name' => $anotherUserFolderRoleName])->all())->assertOk();
        $this->updateRoleResponse($parameters->replace(['name' => $userSecondFolderRoleName])->all())->assertOk();
    }

    #[Test]
    public function willReturnConflictWhenRoleNameAlreadyExistForFolder(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        $role = $this->createRole(folder: $folder);

        $this->loginUser($user);
        $this->updateRoleResponse([
            'name'      => $role->name,
            'folder_id' => $folder->id,
            'role_id'   => $role->id
        ])->assertConflict()->assertJsonFragment(['message' => 'DuplicateRoleName']);

        $this->assertEquals($role->name, $role->refresh()->name);
    }

    #[Test]
    public function willReturnNotFoundWhenRoleDoesNotExists(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        $role = $this->createRole(folder: $folder);

        $this->loginUser($user);
        $this->updateRoleResponse([
            'name'      => $role->name,
            'folder_id' => $folder->id,
            'role_id'   => $role->id + 1
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenRoleIsNotAttachedToFolderOrDoesNotBelongToUser(): void
    {
        $user = UserFactory::new()->create();
        $userFolderRole = $this->createRole(folder: $folder = FolderFactory::new()->for($user)->create());

        $anotherUserFolderRole = $this->createRole();

        $this->loginUser($user);
        $this->updateRoleResponse([
            'name'      => $userFolderRole->name,
            'folder_id' => FolderFactory::new()->for($user)->create()->id,
            'role_id'   => $userFolderRole->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->updateRoleResponse([
            'name'      => 'foo',
            'folder_id' => $folder->id,
            'role_id'   => $anotherUserFolderRole->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);
    }

    #[Test]
    public function whenFolderDoesNotBelongToUser(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        $role = $this->createRole(folder: $folder = FolderFactory::new()->create());

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($user);
        $this->updateRoleResponse($query = [
            'name'      => $role->name,
            'folder_id' => $folder->id,
            'role_id'   => $role->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser($collaborator);
        $this->updateRoleResponse($query)->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertEquals($role->name, $role->refresh()->name);
    }

    #[Test]
    public function whenFolderDoesNotExists(): void
    {
        $folder = FolderFactory::new()->create();

        $this->loginUser(UserFactory::new()->create());
        $this->updateRoleResponse([
            'role_id'   => 3,
            'name'      => $this->faker->word,
            'folder_id' => $folder->id + 1
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}
