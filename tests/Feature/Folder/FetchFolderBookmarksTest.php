<?php

namespace Tests\Feature\Folder;

use App\Enums\Permission;
use App\Models\Favorite;
use App\Services\Folder\AddBookmarksToFolderService;
use App\Services\Folder\MuteCollaboratorService;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\WillCheckBookmarksHealth;

class FetchFolderBookmarksTest extends TestCase
{
    use WillCheckBookmarksHealth;
    use AssertValidPaginationData;
    use CreatesCollaboration;

    private AddBookmarksToFolderService $addBookmarksToFolder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addBookmarksToFolder = app(AddBookmarksToFolderService::class);
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
        Passport::actingAs(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'folderBookmarks', ['folder_id' => 30]);
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->folderBookmarksResponse(['folder_id' => 'f00'])->assertNotFound();
    }

    #[Test]
    public function whenFolderVisibilityIsPrivate(): void
    {
        [$folderOwner, $user] = UserFactory::times(2)->create();

        $privateFolder = FolderFactory::new()->private()->for($folderOwner)->create();
        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->add($privateFolder->id, $bookmark->id);

        //when user is not loggedIn
        $this->folderBookmarksResponse($query = ['folder_id' => $privateFolder->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);

        Passport::actingAs($user);
        $this->folderBookmarksResponse($query)
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);

        Passport::actingAs($folderOwner);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function whenFolderVisibilityIsCollaboratorsOnly(): void
    {
        [$folderOwner, $user, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->visibleToCollaboratorsOnly()->for($folderOwner)->create();
        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmark->id);

        $this->CreateCollaborationRecord($collaborator, $folder);

        //when user is not loggedIn
        $this->folderBookmarksResponse($query = ['folder_id' => $folder->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);

        Passport::actingAs($user);
        $this->folderBookmarksResponse($query)
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);

        Passport::actingAs($folderOwner);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');

        Passport::actingAs($collaborator);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function whenFolderVisibilityIsPublic(): void
    {
        [$folderOwner, $user, $collaborator] = UserFactory::times(3)->create();

        $publicFolder = FolderFactory::new()->for($folderOwner)->create();
        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->add($publicFolder->id, $bookmark->id);

        $this->CreateCollaborationRecord($collaborator, $publicFolder);

        //when user is not loggedIn
        $this->folderBookmarksResponse($query = ['folder_id' => $publicFolder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Passport::actingAs($user);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');

        Passport::actingAs($folderOwner);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');

        Passport::actingAs($collaborator);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function whenFolderIsPasswordProtected(): void
    {
        [$folderOwner, $user] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->passwordProtected()->create();
        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmark->id);

        //when user is not loggedIn
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertBadRequest()
            ->assertExactJson(['message' => 'PasswordRequired']);

        $this->folderBookmarksResponse(['folder_id' => $folder->id, 'folder_password' => 'notPassword'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'InvalidFolderPassword']);

        $this->folderBookmarksResponse($query = ['folder_id' => $folder->id, 'folder_password' => 'password'])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->loginUser($user);
        $this->folderBookmarksResponse($query)->assertOk()->assertJsonCount(1, 'data');

        $this->loginUser($folderOwner);
        //owner does not need password to access
        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk()->assertJsonCount(1, 'data');
    }

    public function testFolderOwnerCanViewHiddenBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIds = BookmarkFactory::new()->for($user)->count(2)->create()->pluck('id')->all();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmarkIds, [$bookmarkIds[0]]);

        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $expect = fn (int $id) => in_array($id, $bookmarkIds, true))
            ->assertJsonPath('data.1.attributes.id', $expect);
    }

    #[Test]
    public function userWithAnyPermissionCanViewFolderBookmarks(): void
    {
        $folderOwner = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();
        $bookmark = BookmarkFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmark->id);

        $collaborator = UserFactory::new()->create();
        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->loginUser($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk()->assertJsonCount(1, 'data');

        $collaborator = UserFactory::new()->create();
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);
        $this->loginUser($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk()->assertJsonCount(1, 'data');

        $collaborator = UserFactory::new()->create();
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);
        $this->loginUser($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk()->assertJsonCount(1, 'data');

        $collaborator = UserFactory::new()->create();
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);
        $this->loginUser($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk()->assertJsonCount(1, 'data');

        $collaborator = UserFactory::new()->create();
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);
        $this->loginUser($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk()->assertJsonCount(1, 'data');
    }

    public function testBookmarkBelongsToAuthUser(): void
    {
        $user = UserFactory::new()->create();
        $collaboratorBookmark = BookmarkFactory::new()->create();
        $authUserBookmark = BookmarkFactory::new()->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->add($folder->id, [$authUserBookmark->id, $collaboratorBookmark->id]);

        //when user is not logged in.
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.belongs_to_auth_user', false)
            ->assertJsonPath('data.1.attributes.belongs_to_auth_user', false);

        Passport::actingAs($user);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.belongs_to_auth_user', false)
            ->assertJsonPath('data.1.attributes.belongs_to_auth_user', true);
    }

    public function testWillSortByLatestByDefault(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmarks[0]->id);
        $this->addBookmarksToFolder->add($folder->id, $bookmarks[1]->id);

        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarks[1]->id)
            ->assertJsonPath('data.1.attributes.id', $bookmarks[0]->id);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(5)->for($user)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmarkIDs->all());

        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk();

        $this->assertBookmarksHealthWillBeChecked($bookmarkIDs->all());
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->folderBookmarksResponse(['folder_id' => $folder->id + 1])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnEmptyResponseWhenFolderHasNoBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillReturnNotFoundWhenFolderOwnerHasDeletedAccount(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $folderOwner->delete();

        Passport::actingAs($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertNotFound()
            ->assertExactJson(['message' => "FolderNotFound"]);

        $this->loginUser(UserFactory::new()->create());
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertNotFound()
            ->assertExactJson(['message' => "FolderNotFound"]);
    }

    public function testFavoriteParametersForBookmarkOwner(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($collaborator)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->addBookmarksToFolder->add($folder->id, $collaboratorBookmark->id);
        Favorite::create(['user_id' => $collaborator->id, 'bookmark_id' => $collaboratorBookmark->id]);

        Passport::actingAs($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.is_user_favorite', true)
            ->assertJsonPath('data.0.attributes.can_favorite', false);

        Passport::actingAs($folderOwner);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.is_user_favorite', false)
            ->assertJsonPath('data.0.attributes.can_favorite', false);
    }

    public function testCanAddBookmarkToFavorites_willBeTrue_whenUserOwnsBookmarks_andBookmarkDoesNotExistInFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmark->id);

        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonPath('data.0.attributes.can_favorite', true);
    }

    public function testCollaboratorCannotViewHiddenBookmarks(): void
    {
        $users = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->for($users[0])->count(2)->create();
        $folder = FolderFactory::new()->for($users[0])->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmarks->pluck('id')->all(), [$bookmarks[0]->id]);

        $this->CreateCollaborationRecord($users[1], $folder);

        Passport::actingAs($users[1]);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarks[1]->id);
    }

    public function testWillReturnOnyHiddenBookmarksWhenUserIsNotLoggedIn(): void
    {
        $bookmarks = BookmarkFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmarks->pluck('id')->all(), [$bookmarks[0]->id]);

        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarks[1]->id);
    }

    #[Test]
    public function willNotIncludeBookmarksFromMutedCollaborators(): void
    {
        [$folderOwner, $collaborator, $user] = UserFactory::times(3)->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $bookmark = BookmarkFactory::new()->for($collaborator)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder->add($folder->id, $bookmark->id);
        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id);

        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->loginUser($user);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->loginUser($folderOwner);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
