<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\CreateFolderBookmarks;
use App\Models\Bookmark;
use App\Models\Folder;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\GeneratesId;
use Tests\Traits\WillCheckBookmarksHealth;

class DeleteFolderTest extends TestCase
{
    use WithFaker;
    use WillCheckBookmarksHealth;
    use GeneratesId;

    private CreateFolderBookmarks $createBookmarkAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBookmarkAction = new CreateFolderBookmarks();
    }

    protected function deleteFolderResponse(string $folderId, array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolder', ['folder_id' => $folderId]), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/{folder_id}', 'deleteFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteFolderResponse($this->generateFolderId()->present())->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->deleteFolderResponse('foo')->assertNotFound();
    }

    public function testDeleteFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->createBookmarkAction->create($folder->id, $bookmarkId = BookmarkFactory::new()->create()->id);

        $this->deleteFolderResponse($folder->public_id->present())->assertOk();

        $this->assertDatabaseMissing(Folder::class, ['id' => $folder->public_id->present()]);
        $this->assertDatabaseHas(Bookmark::class, ['id' => $bookmarkId]);
    }

    public function testDeleteFolderAndItsBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->createBookmarkAction->create($folder->id, $bookmarkId = BookmarkFactory::new()->for($user)->create()->id);

        $this->deleteFolderResponse($folder->public_id->present(), ['delete_bookmarks' => true])->assertOk();

        $this->assertDatabaseMissing(Folder::class, ['id' => $folder->id]);
        $this->assertDatabaseMissing(Bookmark::class, ['id' => $bookmarkId]);
        $this->assertBookmarksHealthWillNotBeChecked([$bookmarkId]);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->public_id->present();

        $this->deleteFolderResponse($folderID)->assertNotFound();
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->deleteFolderResponse($this->generateFolderId()->present())
            ->assertNotFound()
            ->assertJsonFragment(['message' => "FolderNotFound"]);
    }

    public function test_will_not_delete_bookmarks_added_by_collaborators_when_deleting_folder_and_its_bookmarks(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($collaborator)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create(['created_at' => now()]);

        $this->createBookmarkAction->create($folder->id, $collaboratorBookmark->id);

        $this->loginUser($folderOwner);
        $this->deleteFolderResponse($folder->public_id->present(), ['delete_bookmarks' => true])->assertOk();

        $this->assertDatabaseHas(Bookmark::class, ['id' => $collaboratorBookmark->id]);
    }
}
