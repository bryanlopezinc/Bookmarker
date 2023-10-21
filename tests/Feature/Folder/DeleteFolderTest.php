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
    use WithFaker, WillCheckBookmarksHealth;

    protected function deleteFolderResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders', 'createFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteFolderResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteFolderResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder']);

        $this->deleteFolderResponse(['folder' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder']);
    }

    public function testDeleteFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        /** @var AddBookmarksToFolderService */
        $service = app(AddBookmarksToFolderService::class);

        $service->add($folder->id, $bookmarkId = BookmarkFactory::new()->create()->id);

        $this->deleteFolderResponse(['folder' => $folder->id])->assertOk();

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

        $this->deleteFolderResponse([
            'folder'           => $folder->id,
            'delete_bookmarks' => true
        ])->assertOk();

        $this->assertDatabaseMissing(Folder::class, ['id' => $folder->id]);
        $this->assertDatabaseMissing(Bookmark::class, ['id' => $bookmarkId]);
        $this->assertBookmarksHealthWillNotBeChecked([$bookmarkId]);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->deleteFolderResponse(['folder' => $folderID])->assertNotFound();
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->deleteFolderResponse(['folder' => $folderID + 1])
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
        $this->deleteFolderResponse([
            'folder'           => $folderID,
            'delete_bookmarks' => true
        ])->assertOk();

        $this->assertDatabaseHas(Bookmark::class, ['id' => $collaboratorBookmark->id]);
    }
}
