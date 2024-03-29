<?php

namespace Tests\Feature\Folder;

use App\Models\Bookmark;
use App\Models\Folder;
use App\Services\Folder\AddBookmarksToFolderService;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;

class DeleteFolderTest extends TestCase
{
    use WithFaker;
    use WillCheckBookmarksHealth;

    protected function deleteFolderResponse($folderId = 50, array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolder', ['folder_id' => $folderId]), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/{folder_id}', 'deleteFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteFolderResponse()->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteFolderResponse('foo')->assertNotFound();
    }

    public function testDeleteFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        /** @var AddBookmarksToFolderService */
        $service = app(AddBookmarksToFolderService::class);

        $service->add($folder->id, $bookmarkId = BookmarkFactory::new()->create()->id);

        $this->deleteFolderResponse($folder->id)->assertOk();

        $this->assertDatabaseMissing(Folder::class, ['id' => $folder->id]);
        $this->assertDatabaseHas(Bookmark::class, ['id' => $bookmarkId]);
    }

    public function testDeleteFolderAndItsBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        /** @var AddBookmarksToFolderService */
        $service = app(AddBookmarksToFolderService::class);

        $service->add($folder->id, $bookmarkId = BookmarkFactory::new()->for($user)->create()->id);

        $this->deleteFolderResponse($folder->id, ['delete_bookmarks' => true])->assertOk();

        $this->assertDatabaseMissing(Folder::class, ['id' => $folder->id]);
        $this->assertDatabaseMissing(Bookmark::class, ['id' => $bookmarkId]);
        $this->assertBookmarksHealthWillNotBeChecked([$bookmarkId]);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->deleteFolderResponse($folderID)->assertNotFound();
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->deleteFolderResponse($folderID + 1)
            ->assertNotFound()
            ->assertExactJson(['message' => "FolderNotFound"]);
    }

    public function test_will_not_delete_bookmarks_added_by_collaborators_when_deleting_folder_and_its_bookmarks(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($collaborator)->create();
        $folderID = FolderFactory::new()->for($folderOwner)->create(['created_at' => now()])->id;

        /** @var AddBookmarksToFolderService */
        $service = app(AddBookmarksToFolderService::class);

        $service->add($folderID, $collaboratorBookmark->id);

        Passport::actingAs($folderOwner);
        $this->deleteFolderResponse($folderID, ['delete_bookmarks' => true])->assertOk();

        $this->assertDatabaseHas(Bookmark::class, ['id' => $collaboratorBookmark->id]);
    }
}
