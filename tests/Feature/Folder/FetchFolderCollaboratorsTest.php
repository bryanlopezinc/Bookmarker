<?php

namespace Tests\Feature\Folder;

use App\Models\Folder;
use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
use App\Models\User;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\TestResponse as Response;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchFolderCollaboratorsTest extends TestCase
{
    protected function fetchCollaboratorsResponse(array $parameters = []): Response
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

    public function testFilterParameterMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->make());

        $this->fetchCollaboratorsResponse(['permissions' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);

        $this->fetchCollaboratorsResponse(['permissions' => 'addBookmarks,addBookmarks'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "permissions.0" => [
                    "The permissions.0 field has a duplicate value."
                ],
                "permissions.1" => [
                    "The permissions.1 field has a duplicate value."
                ]
            ]);

        //Assert cannot request collaborators with view_only permissions and any other permission
        $this->fetchCollaboratorsResponse([
            'folder_id' => 4,
            'permissions' => 'view_only,addBookmarks'
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'Cannot request collaborator with only view permissions with any other permission']);
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

        $collaboratorIDs = UserFactory::times(5)
            ->create()
            ->tap(function (Collection $collaborators) use ($userFolder) {
                /** @var int */
                $permissionID = FolderPermission::all(['id'])->pluck(['id'])->random();

                $this->createUserFolderAccess($collaborators, $userFolder->id, $permissionID);
            })
            ->pluck('id');

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolder->id])
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->collect('data')
            ->tap(fn (Collection $response) => $this->assertCount(5, $response->pluck('attributes.id')->uniqueStrict())) // assert no dupes
            ->each(function (array $data) use ($collaboratorIDs) {
                (new AssertableJsonString($data))
                    ->assertPath('type', 'folderCollaborator')
                    ->assertPath('attributes.id', fn (int $collaboratorID) => $collaboratorIDs->containsStrict($collaboratorID))
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
    }

    public function testWillReturnOnlyCollaboratorsWithSpecifiedPermissions(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderID = FolderFactory::new()->for($user)->create()->id;
        $permissions = FolderPermission::all(['id', 'name']);
        $collaboratorWithAllPermissions = UserFactory::new()->create();
        $collaboratorThatCanOnlyViewBookmarks = UserFactory::new()->create();

        $this->createUserFolderAccess(
            $collaboratorThatCanOnlyViewBookmarks,
            $folderID,
            $permissions->where('name', FolderPermission::VIEW_BOOKMARKS)->sole()->id
        );

        /** @var Collection */
        list(
            $collaboratorsThatCanOnlyAddBookmarks,
            $collaboratorsThatCanOnlyRemoveBookmarks,
            $collaboratorsThatCanOnlyInviteUser,
            $collaboratorsThatCanOnlyUpdateFolder,
        ) = UserFactory::times(20)
            ->create()
            ->chunk(5)
            ->map(fn (Collection $users) => $users->toBase()->merge([$collaboratorWithAllPermissions]))
            ->each(function (Collection $users, int $index) use ($permissions, $folderID) {
                /** @var array<string,int> */
                $nameIDMap = $permissions->mapWithKeys(fn (FolderPermission $p) => [$p->name => $p->id])->all();

                //collaborator type as assigned in list
                $sequence = [
                    FolderPermission::ADD_BOOKMARKS,
                    FolderPermission::DELETE_BOOKMARKS,
                    FolderPermission::INVITE,
                    FolderPermission::UPDATE_FOLDER,
                ];

                $this->createUserFolderAccess($users, $folderID, $nameIDMap[$sequence[$index]]);
            });

        $this->willReturnCollaboratorsWithPermissions($folderID, 'addBookmarks', fn (Response $r) =>
        $r->assertOk()
            ->assertJsonCount(6, 'data') //collaborator with all permissions makes it six
            ->collect('data')
            ->tap(function (Collection $response) use ($collaboratorsThatCanOnlyAddBookmarks) {
                $collaboratorIDs = $response->pluck('attributes.id')->sortDesc()->values();

                $this->assertCount(6, $collaboratorIDs->uniqueStrict()); // assert no duplicates
                $this->assertEquals($collaboratorIDs->all(), $collaboratorsThatCanOnlyAddBookmarks->pluck('id')->sortDesc()->values()->all());
            }));

        $this->willReturnCollaboratorsWithPermissions($folderID, 'updateFolder', fn (Response $r)  =>
        $r->assertOk()
            ->assertJsonCount(6, 'data')
            ->collect('data')
            ->tap(function (Collection $response) use ($collaboratorsThatCanOnlyUpdateFolder) {
                $collaboratorIDs = $response->pluck('attributes.id')->sortDesc()->values();

                $this->assertCount(6, $collaboratorIDs->uniqueStrict());
                $this->assertEquals($collaboratorIDs->all(), $collaboratorsThatCanOnlyUpdateFolder->pluck('id')->sortDesc()->values()->all());
            }));

        $this->willReturnCollaboratorsWithPermissions($folderID, 'removeBookmarks', fn (Response $r) =>
        $r->assertOk()
            ->assertJsonCount(6, 'data')
            ->collect('data')
            ->tap(function (Collection $response) use ($collaboratorsThatCanOnlyRemoveBookmarks) {
                $collaboratorIDs = $response->pluck('attributes.id')->sortDesc()->values();

                $this->assertCount(6, $collaboratorIDs->uniqueStrict());
                $this->assertEquals($collaboratorIDs->all(), $collaboratorsThatCanOnlyRemoveBookmarks->pluck('id')->sortDesc()->values()->all());
            }));

        $this->willReturnCollaboratorsWithPermissions($folderID, 'inviteUser', fn (Response $r) =>
        $r->assertOk()
            ->assertJsonCount(6, 'data')
            ->collect('data')
            ->tap(function (Collection $response) use ($collaboratorsThatCanOnlyInviteUser) {
                $collaboratorIDs = $response->pluck('attributes.id')->sortDesc()->values();

                $this->assertCount(6, $collaboratorIDs->uniqueStrict());
                $this->assertEquals($collaboratorIDs->all(), $collaboratorsThatCanOnlyInviteUser->pluck('id')->sortDesc()->values()->all());
            }));

        $this->willReturnCollaboratorsWithPermissions($folderID, 'view_only', fn (Response $r) =>
        $r->assertOk()
            ->assertJsonCount(1, 'data')
            ->collect('data')
            ->each(function (array $data) use ($collaboratorThatCanOnlyViewBookmarks) {
                $this->assertTrue($collaboratorThatCanOnlyViewBookmarks->pluck('id')->containsStrict($data['attributes']['id']));
            }));

        foreach ([
            'inviteUser,removeBookmarks',
            'inviteUser,removeBookmarks,updateFolder',
            'inviteUser,removeBookmarks,updateFolder,addBookmarks',
        ] as $permissions) {
            $this->willReturnCollaboratorsWithPermissions($folderID, $permissions, fn (Response $r) =>
            $r->assertOk()
                ->assertJsonCount(1, 'data')
                ->collect('data')
                ->each(function (array $data) use ($collaboratorWithAllPermissions) {
                    $this->assertEquals($collaboratorWithAllPermissions->id, $data['attributes']['id']);
                }));
        }
    }

    private function willReturnCollaboratorsWithPermissions(int $folderID, string $permissions, \Closure $assertion): void
    {
        //Little hack to choose a specific test cases to run
        $runOnlyRequestWithPermissions = [/*'inviteUser'*/];

        if (!empty($runOnlyRequestWithFilters) && !in_array($permissions, $runOnlyRequestWithPermissions)) {
            $this->markTestIncomplete('All cases not covered');
            return;
        }

        $assertion($this->fetchCollaboratorsResponse([
            'folder_id' => $folderID,
            'permissions' => $permissions
        ]));
    }

    private function createUserFolderAccess(Collection|User $collaborators, int $folderID, int $permissionID): void
    {
        if ($collaborators instanceof User) {
            $collaborators = collect([$collaborators]);
        }

        FolderCollaboratorPermission::insert(
            $collaborators->map(fn (User $collaborator) => [
                'folder_id' => $folderID,
                'user_id' => $collaborator->id,
                'permission_id' => $permissionID,
                'created_at' => now()
            ])->all()
        );
    }

    public function testWillReturnCorrectPermissions(): void
    {
        [$folderOwner, $collaborator, $anotherCollaborator] = UserFactory::times(3)->create();
        $userFolderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($userFolderID)
            ->addBookmarksPermission()
            ->create();

        FolderCollaboratorPermissionFactory::new()
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

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($userFolderID)
            ->create();

        FolderCollaboratorPermissionFactory::new()
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

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($userFolderID)
            ->addBookmarksPermission()
            ->create();

        FolderCollaboratorPermissionFactory::new()
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

    public function testWillReturnOnlyCollaboratorPermissionsToFolder(): void
    {
        [$me, $you, $collaborator] = UserFactory::new()->count(3)->create();

        $myFolder = FolderFactory::new()->for($me)->create()->id;
        $yourFolder = FolderFactory::new()->for($you)->create()->id;

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($myFolder)
            ->inviteUser()
            ->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($yourFolder)
            ->addBookmarksPermission()
            ->create();

        Passport::actingAs($me);
        $this->fetchCollaboratorsResponse(['folder_id' => $myFolder])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'canInviteUsers' => true,
                'canAddBookmarks' => false,
                'canRemoveBookmarks' => false,
                'canUpdateFolder' => false
            ]);;
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

                FolderCollaboratorPermission::insert(
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
