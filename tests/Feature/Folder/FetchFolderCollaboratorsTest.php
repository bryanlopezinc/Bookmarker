<?php

namespace Tests\Feature\Folder;

use App\Models\Folder;
use App\Models\FolderAccess;
use App\Models\FolderPermission;
use App\Models\User;
use Database\Factories\FolderAccessFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\Fluent\AssertableJson as AssertJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchFolderCollaboratorsTest extends TestCase
{
    protected function fetchCollaboratorsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchFolderCollaborators', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/collaborators', 'fetchFolderCollaborators');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->fetchCollaboratorsResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchCollaboratorsResponse()->assertJsonValidationErrors(['folder_id']);
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchCollaboratorsResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->fetchCollaboratorsResponse(['per_page' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);

        $this->fetchCollaboratorsResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->fetchCollaboratorsResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);
    }

    public function testWillFetchCollaborators(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());
        $userFolder = FolderFactory::new()->for($user)->create();

        $collaborators = UserFactory::times(5)
            ->create()
            ->tap(function (Collection $collaborators) use ($userFolder) {
                $permissionIDs = FolderPermission::all(['id'])->pluck(['id']);

                FolderAccess::insert(
                    $collaborators->map(fn (User $collaborator) => [
                        'folder_id' => $userFolder->id,
                        'user_id' => $collaborator->id,
                        'permission_id' => $permissionIDs->random(),
                        'created_at' => now()
                    ])->all()
                );
            });

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolder->id])
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson(function (AssertJson $json) use ($collaborators) {
                $json->etc()
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertJson $json) use ($collaborators) {
                        $json->etc();
                        $json->where('type', 'folderCollaborator');
                        $json->where('attributes.id', function (int $collaboratorID) use ($collaborators) {
                            return $collaborators->pluck('id')->containsStrict($collaboratorID);
                        });

                        (new AssertableJsonString($json->toArray()))
                            ->assertCount(4, 'attributes')
                            ->assertCount(4, 'attributes.permissions')
                            ->assertStructure([
                                'type',
                                'attributes' => [
                                    'id',
                                    'firstname',
                                    'lastname',
                                    'permissions' => [
                                        'canInviteUsers',
                                        'canAddBookmarks',
                                        'canRemoveBookmarks',
                                        'canUpdateFolder'
                                    ],
                                ]
                            ]);
                    });
            });
    }

    public function testWillReturnCorrectPermissions(): void
    {
        [$folderOwner, $collaborator, $anotherCollaborator] = UserFactory::times(3)->create();
        $userFolderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($userFolderID)
            ->addBookmarksPermission()
            ->create();

        FolderAccessFactory::new()
            ->user($anotherCollaborator->id)
            ->folder($userFolderID)
            ->inviteUser()
            ->create();

        Passport::actingAs($folderOwner);
        $response = $this->fetchCollaboratorsResponse(['folder_id' => $userFolderID])->assertOk();

        $this->assertTrue($response->json('data.0.attributes.permissions.canAddBookmarks'));
        $this->assertFalse($response->json('data.0.attributes.permissions.canInviteUsers'));
        $this->assertFalse($response->json('data.0.attributes.permissions.canRemoveBookmarks'));

        $this->assertTrue($response->json('data.1.attributes.permissions.canInviteUsers'));
        $this->assertFalse($response->json('data.1.attributes.permissions.canAddBookmarks'));
        $this->assertFalse($response->json('data.1.attributes.permissions.canRemoveBookmarks'));
    }

    public function testFolderMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchCollaboratorsResponse(['folder_id' => FolderFactory::new()->create()->id])->assertForbidden();
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->fetchCollaboratorsResponse([
            'folder_id' => $folder->id + 1
        ])->assertNotFound();
    }

    public function testWillReturnEmptyJsonWhenFolderHasNoCollaborators(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userFolder = FolderFactory::new()->for($user)->create();

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillNotIncludeDeletedUserAccountsInResponse(): void
    {
        [$folderOwner, $collaborator, $anotherCollaborator] = UserFactory::times(3)->create();
        $userFolderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($userFolderID)
            ->create();

        FolderAccessFactory::new()
            ->user($anotherCollaborator->id)
            ->folder($userFolderID)
            ->create();

        Passport::actingAs($collaborator);
        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();

        Passport::actingAs($folderOwner);
        $this->fetchCollaboratorsResponse(['folder_id' => $userFolderID])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $anotherCollaborator->id
            ]);
    }

    public function testWillNotReturnDuplicateRecords(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(3)->create();
        $userFolderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($userFolderID)
            ->addBookmarksPermission()
            ->create();

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($userFolderID)
            ->inviteUser()
            ->create();

        Passport::actingAs($folderOwner);

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolderID])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonFragment([
                'canInviteUsers' => true,
                'canAddBookmarks' => true,
                'canRemoveBookmarks' => false,
                'canUpdateFolder' => false
            ]);
    }

    public function testWillReturnOnlyCollaboratorsForSpecifiedFolder(): void
    {
        $folderOwner = UserFactory::new()->create();
        $userFolderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderFactory::new()
            ->count(3)
            ->for($folderOwner)
            ->create()
            ->tap(function (Collection $folders) {
                $permissionIDs = FolderPermission::all(['id'])->pluck(['id']);

                FolderAccess::insert(
                    $folders->map(fn (Folder $folder) => [
                        'folder_id' => $folder->id,
                        'user_id' => UserFactory::new()->create()->id,
                        'permission_id' => $permissionIDs->random(),
                        'created_at' => now()
                    ])->all()
                );
            });

        Passport::actingAs($folderOwner);

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolderID])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
