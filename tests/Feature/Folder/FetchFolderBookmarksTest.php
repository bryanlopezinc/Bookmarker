<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\CreateFolderBookmarks;
use App\Collections\BookmarkPublicIdsCollection as PublicIds;
use App\Enums\Permission;
use App\Models\Favorite;
use App\Services\Folder\MuteCollaboratorService;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\GeneratesId;
use Tests\Traits\WillCheckBookmarksHealth;

class FetchFolderBookmarksTest extends TestCase
{
    use WillCheckBookmarksHealth;
    use AssertValidPaginationData;
    use CreatesCollaboration;
    use GeneratesId;

    private CreateFolderBookmarks $addBookmarksToFolder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addBookmarksToFolder = new CreateFolderBookmarks();
    }

    protected function folderBookmarksResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('folderBookmarks', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/bookmarks', 'folderBookmarks');
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'folderBookmarks', ['folder_id' => $this->generateFolderId()->present()]);
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->folderBookmarksResponse(['folder_id' => 'f00'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function whenFolderVisibilityIsPrivate(): void
    {
        [$folderOwner, $user] = UserFactory::times(2)->create();

        $privateFolder = FolderFactory::new()->private()->for($folderOwner)->create();
        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->create($privateFolder->id, $bookmark->id);

        //when user is not loggedIn
        $this->folderBookmarksResponse($query = ['folder_id' => $privateFolder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser($user);
        $this->folderBookmarksResponse($query)
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser($folderOwner);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function whenFolderVisibilityIsCollaboratorsOnly(): void
    {
        [$folderOwner, $user, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->visibleToCollaboratorsOnly()->for($folderOwner)->create();
        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmark->id);

        $this->CreateCollaborationRecord($collaborator, $folder);

        //when user is not loggedIn
        $this->folderBookmarksResponse($query = ['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser($user);
        $this->folderBookmarksResponse($query)
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser($folderOwner);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');

        $this->loginUser($collaborator);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function whenFolderVisibilityIsPublic(): void
    {
        [$folderOwner, $user, $collaborator] = UserFactory::times(3)->create();

        $publicFolder = FolderFactory::new()->for($folderOwner)->create();
        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->create($publicFolder->id, $bookmark->id);

        $this->CreateCollaborationRecord($collaborator, $publicFolder);

        //when user is not loggedIn
        $this->folderBookmarksResponse($query = ['folder_id' => $publicFolder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->loginUser($user);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');

        $this->loginUser($folderOwner);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');

        $this->loginUser($collaborator);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function whenFolderIsPasswordProtected(): void
    {
        [$folderOwner, $user] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->passwordProtected()->create();
        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmark->id);

        //when user is not loggedIn
        $this->folderBookmarksResponse(['folder_id' => $id = $folder->public_id->present()])
            ->assertBadRequest()
            ->assertJsonFragment(['message' => 'PasswordRequired']);

        $this->folderBookmarksResponse(['folder_id' => $id, 'folder_password' => 'notPassword'])
            ->assertUnauthorized()
            ->assertJsonFragment(['message' => 'InvalidFolderPassword']);

        $this->folderBookmarksResponse($query = ['folder_id' => $id, 'folder_password' => 'password'])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->loginUser($user);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');

        $this->loginUser($folderOwner);
        //owner does not need password to access
        $this->folderBookmarksResponse(['folder_id' => $id])->assertOk()->assertJsonCount(1, 'data');
    }

    public function testFolderOwnerCanViewHiddenBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->for($user)->count(2)->create();
        $bookmarkIds = $bookmarks->pluck('id')->all();
        $bookmarksPublicIds = PublicIds::fromObjects($bookmarks)->present()->all();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarkIds, [$bookmarkIds[0]]);

        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $expect = fn (string $id) => in_array($id, $bookmarksPublicIds, true))
            ->assertJsonPath('data.1.attributes.id', $expect);
    }

    #[Test]
    #[DataProvider('permissions')]
    public function userWithAnyPermissionCanViewFolderBookmarks(array $permissions): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmark->id);

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);

        $this->loginUser($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public static function permissions(): array
    {
        return  [
            'no permissions'            => [[]],
            'Add bookmarks'             => [[Permission::ADD_BOOKMARKS]],
            'Remove bookmarks'          => [[Permission::DELETE_BOOKMARKS]],
            'Invite users'              => [[Permission::INVITE_USER]],
            'Update folder name'        => [[Permission::UPDATE_FOLDER_NAME]],
            'Update folder description' => [[Permission::UPDATE_FOLDER_DESCRIPTION]],
            'Update folder thumbnail'   => [[Permission::UPDATE_FOLDER_THUMBNAIL]],
        ];
    }

    public function testBookmarkBelongsToAuthUser(): void
    {
        $user = UserFactory::new()->create();
        $collaboratorBookmark = BookmarkFactory::new()->create();
        $authUserBookmark = BookmarkFactory::new()->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, [$authUserBookmark->id, $collaboratorBookmark->id]);

        //when user is not logged in.
        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.belongs_to_auth_user', false)
            ->assertJsonPath('data.1.attributes.belongs_to_auth_user', false);

        $this->loginUser($user);
        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.belongs_to_auth_user', false)
            ->assertJsonPath('data.1.attributes.belongs_to_auth_user', true);
    }

    public function testWillSortByLatestByDefault(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        $bookmarkIds = PublicIds::fromObjects($bookmarks)->present()->all();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarks[0]->id);
        $this->addBookmarksToFolder->create($folder->id, $bookmarks[1]->id);

        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarkIds[1])
            ->assertJsonPath('data.1.attributes.id', $bookmarkIds[0]);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(5)->for($user)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarkIDs->all());

        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        $this->assertBookmarksHealthWillBeChecked($bookmarkIDs->all());
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $folder->delete();

        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnEmptyResponseWhenFolderHasNoBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillReturnNotFoundWhenFolderOwnerHasDeletedAccount(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $folderOwner->delete();

        $this->loginUser($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $id = $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => "FolderNotFound"]);

        $this->loginUser(UserFactory::new()->create());
        $this->folderBookmarksResponse(['folder_id' => $id])
            ->assertNotFound()
            ->assertJsonFragment(['message' => "FolderNotFound"]);
    }

    public function testFavoriteParametersForBookmarkOwner(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($collaborator)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->addBookmarksToFolder->create($folder->id, $collaboratorBookmark->id);
        Favorite::create(['user_id' => $collaborator->id, 'bookmark_id' => $collaboratorBookmark->id]);

        $this->loginUser($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $id = $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.is_user_favorite', true)
            ->assertJsonPath('data.0.attributes.can_favorite', false);

        $this->loginUser($folderOwner);
        $this->folderBookmarksResponse(['folder_id' => $id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.is_user_favorite', false)
            ->assertJsonPath('data.0.attributes.can_favorite', false);
    }

    public function testCanAddBookmarkToFavorites_willBeTrue_whenUserOwnsBookmarks_andBookmarkDoesNotExistInFavorites(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmark->id);

        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonPath('data.0.attributes.can_favorite', true);
    }

    public function testCollaboratorCannotViewHiddenBookmarks(): void
    {
        $users = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->for($users[0])->count(2)->create();
        $folder = FolderFactory::new()->for($users[0])->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarks->pluck('id')->all(), [$bookmarks[0]->id]);

        $this->CreateCollaborationRecord($users[1], $folder);

        $this->loginUser($users[1]);
        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarks[1]->public_id->present());
    }

    public function testWillReturnOnyHiddenBookmarksWhenUserIsNotLoggedIn(): void
    {
        $bookmarks = BookmarkFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarks->pluck('id')->all(), [$bookmarks[0]->id]);

        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());
        $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarks[1]->public_id->present());
    }

    #[Test]
    public function willNotIncludeBookmarksFromMutedCollaborators(): void
    {
        [$folderOwner, $collaborator, $user] = UserFactory::times(3)->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $bookmark = BookmarkFactory::new()->for($collaborator)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmark->id);
        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id);

        $this->folderBookmarksResponse(['folder_id' => $id = $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->loginUser($user);
        $this->folderBookmarksResponse(['folder_id' => $id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->loginUser($folderOwner);
        $this->folderBookmarksResponse(['folder_id' => $id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function willIncludeBookmarksFromMutedCollaboratorsWhenMuteDurationIsPast(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $bookmark = BookmarkFactory::new()->for($collaborator)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmark->id);
        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id, now(), 2);

        $this->loginUser($folderOwner);
        $this->travel(119)->minutes(function () use ($folder) {
            $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
                ->assertOk()
                ->assertJsonCount(0, 'data');
        });

        $this->travel(121)->minutes(function () use ($folder) {
            $this->folderBookmarksResponse(['folder_id' => $folder->public_id->present()])
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });
    }
}
