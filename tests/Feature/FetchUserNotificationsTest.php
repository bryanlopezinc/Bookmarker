<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Str;
use App\ValueObjects\UserID;
use Laravel\Passport\Passport;
use App\ValueObjects\ResourceID;
use Illuminate\Support\Collection;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use Database\Factories\BookmarkFactory;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\Fluent\AssertableJson;
use App\Notifications\FolderUpdatedNotification;
use App\Collections\ResourceIDsCollection as IDs;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\Enums\NotificationType;
use App\Notifications\BookmarksAddedToFolderNotification;
use App\Notifications\NewCollaboratorNotification;
use App\Notifications\BookmarksRemovedFromFolderNotification;
use App\Notifications\CollaboratorExitNotification;
use Illuminate\Notifications\DatabaseNotification;

class FetchUserNotificationsTest extends TestCase
{
    private function userNotificationsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserNotifications', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/notifications', 'fetchUserNotifications');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->userNotificationsResponse()->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenPaginationDataIsInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->userNotificationsResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);

        $this->userNotificationsResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->userNotificationsResponse(['per_page' => 14])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);;

        $this->userNotificationsResponse(['per_page' => 40])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);
    }

    public function testWillFetchUserNotifications(): void
    {
        [$user, $tom, $sean, $taylor] = UserFactory::times(4)->create();
        $tomsBookmarks = BookmarkFactory::times(3)->create(['user_id' => $tom->id])->pluck('id');
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $notifications =  [
            new BookmarksAddedToFolderNotification(IDs::fromNativeTypes($tomsBookmarks), new ResourceID($folder->id), new UserID($tom->id)),
            new BookmarksRemovedFromFolderNotification(IDs::fromNativeTypes($tomsBookmarks), new ResourceID($folder->id), new UserID($sean->id)),
            new NewCollaboratorNotification(new UserID($sean->id), new ResourceID($folder->id), new UserID($taylor->id)),
            new FolderUpdatedNotification(
                FolderBuilder::fromModel($folder)->setName('foo')->setTags([])->build(),
                FolderBuilder::fromModel($folder)->setName('baz')->setTags([])->build(),
                new UserID($taylor->id)
            )
        ];

        foreach ($notifications as $notification) {
            $user->notify($notification);
        }

        Passport::actingAs($user);
        $this->userNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function testBookmarksAddedToFolderNotification(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->create(['user_id' => $collaborator->id])->pluck('id');
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $user->notify(
            new BookmarksAddedToFolderNotification(
                IDs::fromNativeTypes($collaboratorBookmarks),
                new ResourceID($folder->id),
                new UserID($collaborator->id)
            )
        );

        Passport::actingAs($user);
        $this->userNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->collect('data')
            ->each(function (array $data) use ($collaborator, $folder) {
                AssertableJson::fromArray($data)
                    ->where('type', 'BookmarksAddedToFolderNotification')
                    ->where('attributes.id', fn (string $id) => Str::isUuid($id))
                    ->where('attributes.collaborator_exists', true)
                    ->where('attributes.folder_exists', true)
                    ->where('attributes.bookmarks_count', 3)
                    ->where('attributes.by_collaborator', function (Collection $collaboratorData) use ($collaborator) {
                        $this->assertEquals($collaborator->id, $collaboratorData['id']);
                        $this->assertEquals($collaborator->firstname, $collaboratorData['first_name']);
                        $this->assertEquals($collaborator->lastname, $collaboratorData['last_name']);
                        return true;
                    })
                    ->where('attributes.folder', function (Collection $folderData) use ($folder) {
                        $this->assertEquals($folder->name, $folderData['name']);
                        $this->assertEquals($folder->id, $folderData['id']);
                        return true;
                    });

                (new  AssertableJsonString($data))
                    ->assertCount(7, 'attributes')
                    ->assertCount(3, 'attributes.by_collaborator')
                    ->assertCount(2, 'attributes.folder')
                    ->assertStructure([
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            "bookmarks_count",
                            "by_collaborator" =>  [
                                "id",
                                "first_name",
                                "last_name",
                            ],
                            "folder" => [
                                "name",
                                "id",
                            ],
                            "bookmarks" => [
                                '*' => ['title']
                            ]
                        ]
                    ]);
            });
    }

    public function testBookmarksRemovedFromFolderNotification(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->create(['user_id' => $collaborator->id])->pluck('id');
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $user->notify(
            new BookmarksRemovedFromFolderNotification(
                IDs::fromNativeTypes($collaboratorBookmarks),
                new ResourceID($folder->id),
                new UserID($collaborator->id)
            )
        );

        Passport::actingAs($user);
        $this->userNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->collect('data')
            ->each(function (array $data) use ($collaborator, $folder) {
                AssertableJson::fromArray($data)
                    ->where('type', 'BookmarksRemovedFromFolderNotification')
                    ->where('attributes.collaborator_exists', true)
                    ->where('attributes.id', fn (string $id) => Str::isUuid($id))
                    ->where('attributes.folder_exists', true)
                    ->where('attributes.bookmarks_count', 3)
                    ->where('attributes.by_collaborator', function (Collection $collaboratorData) use ($collaborator) {
                        $this->assertEquals($collaborator->id, $collaboratorData['id']);
                        $this->assertEquals($collaborator->firstname, $collaboratorData['first_name']);
                        $this->assertEquals($collaborator->lastname, $collaboratorData['last_name']);
                        return true;
                    })
                    ->where('attributes.folder', function (Collection $folderData) use ($folder) {
                        $this->assertEquals($folder->name, $folderData['name']);
                        $this->assertEquals($folder->id, $folderData['id']);
                        return true;
                    })
                    ->where('attributes.bookmarks', function (Collection $bookmarks) {
                        $this->assertCount(3, $bookmarks);
                        return true;
                    });

                (new  AssertableJsonString($data))
                    ->assertCount(7, 'attributes')
                    ->assertCount(3, 'attributes.by_collaborator')
                    ->assertCount(2, 'attributes.folder')
                    ->assertStructure([
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            "bookmarks_count",
                            "by_collaborator" =>  [
                                "id",
                                "first_name",
                                "last_name",
                            ],
                            "folder" => [
                                "name",
                                "id",
                            ],
                            "bookmarks" => [
                                '*' => ['title']
                            ]
                        ]
                    ]);
            });
    }

    public function testCollaboratorAddedToFolderNotification(): void
    {
        [$folderOwner, $collaborator, $newCollaborator] = UserFactory::times(3)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        $folderOwner->notify(
            new NewCollaboratorNotification(
                new UserID($newCollaborator->id),
                new ResourceID($folder->id),
                new UserID($collaborator->id)
            )
        );

        Passport::actingAs($folderOwner);
        $this->userNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->collect('data')
            ->each(function (array $data) use ($collaborator, $folder, $newCollaborator) {
                AssertableJson::fromArray($data)
                    ->where('type', 'CollaboratorAddedToFolderNotification')
                    ->where('attributes.collaborator_exists', true)
                    ->where('attributes.id', fn (string $id) => Str::isUuid($id))
                    ->where('attributes.folder_exists', true)
                    ->where('attributes.new_collaborator_exists', true)
                    ->where('attributes.collaborator', function (Collection $collaboratorData) use ($collaborator) {
                        $this->assertEquals($collaborator->id, $collaboratorData['id']);
                        $this->assertEquals($collaborator->firstname, $collaboratorData['first_name']);
                        $this->assertEquals($collaborator->lastname, $collaboratorData['last_name']);
                        return true;
                    })
                    ->where('attributes.folder', function (Collection $folderData) use ($folder) {
                        $this->assertEquals($folder->name, $folderData['name']);
                        $this->assertEquals($folder->id, $folderData['id']);
                        return true;
                    })
                    ->where('attributes.new_collaborator', function (Collection $newCollaboratorData)  use ($newCollaborator) {
                        $this->assertEquals($newCollaborator->id, $newCollaboratorData['id']);
                        $this->assertEquals($newCollaborator->firstname, $newCollaboratorData['first_name']);
                        $this->assertEquals($newCollaborator->lastname, $newCollaboratorData['last_name']);
                        return true;
                    });

                (new  AssertableJsonString($data))
                    ->assertCount(7, 'attributes')
                    ->assertCount(3, 'attributes.collaborator')
                    ->assertCount(3, 'attributes.new_collaborator')
                    ->assertCount(2, 'attributes.folder')
                    ->assertStructure([
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            "new_collaborator_exists",
                            "collaborator" =>  [
                                "id",
                                "first_name",
                                "last_name",
                            ],
                            "folder" => [
                                "name",
                                "id",
                            ],
                            "new_collaborator" =>  [
                                "id",
                                "first_name",
                                "last_name",
                            ],
                        ]
                    ]);
            });
    }

    public function testFolderUpdatedNotification(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        $folderOwner->notify(
            new FolderUpdatedNotification(
                FolderBuilder::fromModel($folder)->setName('foo')->setTags([])->build(),
                FolderBuilder::fromModel($folder)->setName('baz')->setTags([])->build(),
                new UserID($collaborator->id)
            )
        );

        Passport::actingAs($folderOwner);
        $this->userNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->collect('data')
            ->each(function (array $data) use ($collaborator, $folder) {
                AssertableJson::fromArray($data)
                    ->where('type', 'FolderUpdatedNotification')
                    ->where('attributes.collaborator_exists', true)
                    ->where('attributes.folder_exists', true)
                    ->where('attributes.id', fn (string $id) => Str::isUuid($id))
                    ->where('attributes.collaborator', function (Collection $collaboratorData) use ($collaborator) {
                        $this->assertEquals($collaborator->id, $collaboratorData['id']);
                        $this->assertEquals($collaborator->firstname, $collaboratorData['first_name']);
                        $this->assertEquals($collaborator->lastname, $collaboratorData['last_name']);
                        return true;
                    })
                    ->where('attributes.folder', function (Collection $folderData) use ($folder) {
                        $this->assertEquals($folder->name, $folderData['name']);
                        $this->assertEquals($folder->id, $folderData['id']);
                        return true;
                    })
                    ->where('attributes.changes', function (Collection $folderChanges) {
                        $folderChanges = $folderChanges->toArray();
                        $this->assertCount(1, $folderChanges);
                        $this->assertEquals($folderChanges['name']['from'], 'foo');
                        $this->assertEquals($folderChanges['name']['to'], 'baz');
                        return true;
                    });

                (new  AssertableJsonString($data))
                    ->assertCount(6, 'attributes')
                    ->assertCount(3, 'attributes.collaborator')
                    ->assertCount(1, 'attributes.changes')
                    ->assertCount(2, 'attributes.changes.name')
                    ->assertCount(2, 'attributes.folder')
                    ->assertStructure([
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            "collaborator" =>  [
                                "id",
                                "first_name",
                                "last_name",
                            ],
                            "folder" => [
                                "name",
                                "id",
                            ],
                            "changes" =>  [
                                "name" => [
                                    'from',
                                    'to'
                                ],
                            ],
                        ]
                    ]);
            });
    }

    public function testCollaboratorExitNotification(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        $folderOwner->notify(
            new CollaboratorExitNotification(
                new ResourceID($folder->id),
                new UserID($collaborator->id)
            )
        );

        Passport::actingAs($folderOwner);
        $this->userNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->collect('data')
            ->each(function (array $data) use ($collaborator, $folder) {
                AssertableJson::fromArray($data)
                    ->where('type', 'CollaboratorExitNotification')
                    ->where('attributes.collaborator_exists', true)
                    ->where('attributes.id', fn (string $id) => Str::isUuid($id))
                    ->where('attributes.folder_exists', true)
                    ->where('attributes.collaborator', function (Collection $collaboratorData) use ($collaborator) {
                        $this->assertEquals($collaborator->firstname, $collaboratorData['first_name']);
                        $this->assertEquals($collaborator->lastname, $collaboratorData['last_name']);
                        return true;
                    })
                    ->where('attributes.folder', function (Collection $folderData) use ($folder) {
                        $this->assertEquals($folder->name, $folderData['name']);
                        $this->assertEquals($folder->id, $folderData['id']);
                        return true;
                    });

                (new  AssertableJsonString($data))
                    ->assertCount(5, 'attributes')
                    ->assertCount(2, 'attributes.collaborator')
                    ->assertCount(2, 'attributes.folder')
                    ->assertStructure([
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            "collaborator" =>  [
                                "first_name",
                                "last_name",
                            ],
                            "folder" => [
                                "name",
                                "id",
                            ],
                        ]
                    ]);
            });
    }

    public function testWillReturnEmptyDatasetWhenUserHasNoNotifications(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->userNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillFetchOnlyUnReadNotifications(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $data = (new FolderUpdatedNotification(
            FolderBuilder::fromModel($folder)->setName('foo')->setTags([])->build(),
            FolderBuilder::fromModel($folder)->setName('baz')->setTags([])->build(),
            new UserID(UserFactory::new()->create()->id)
        ))->toDatabase($user);

        DatabaseNotification::query()->create([
            'id' => Str::uuid()->toString(),
            'type' => NotificationType::FOLDER_UPDATED,
            'notifiable_type' => 'user',
            'notifiable_id' => $user->id,
            'data' => $data,
            'read_at' => now()
        ]);

        $this->userNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillFetchOnlyUserNotifications(): void
    {
        [$folderOwner, $collaborator, $user] = UserFactory::times(3)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        $folderOwner->notify(
            new FolderUpdatedNotification(
                FolderBuilder::fromModel($folder)->setName('foo')->setTags([])->build(),
                FolderBuilder::fromModel($folder)->setName('baz')->setTags([])->build(),
                new UserID($collaborator->id)
            )
        );

        Passport::actingAs($user);
        $this->userNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
