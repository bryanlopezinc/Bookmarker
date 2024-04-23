<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\CreateFolderBookmarks;
use App\Collections\BookmarkPublicIdsCollection;
use App\Enums\FolderBookmarkVisibility;
use App\Models\FolderBookmark;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\GeneratesId;

class HideFolderBookmarksTest extends TestCase
{
    use WithFaker;
    use GeneratesId;

    private CreateFolderBookmarks $addBookmarksToFolder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addBookmarksToFolder = new CreateFolderBookmarks();
    }

    protected function hideFolderResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(
            route('hideFolderBookmarks', ['folder_id' => $parameters['folder_id']]),
            Arr::except($parameters, ['folder_id'])
        );
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/bookmarks/hide', 'hideFolderBookmarks');
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->hideFolderResponse(['folder_id' => 'foo', 'bookmarks' => $this->generateBookmarkId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->hideFolderResponse(['folder_id' => 4])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $bookmarkPublicIds = $this->generateBookmarkIds(51)->present();

        $this->loginUser(UserFactory::new()->create());

        $this->hideFolderResponse(['folder_id' => $publicId = $this->generateFolderId()->present()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks']);

        $this->hideFolderResponse(['folder_id' => 44, 'bookmarks' => $bookmarkPublicIds->take(2)->implode(',')])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->hideFolderResponse(['bookmarks' => $bookmarkPublicIds->take(1)->add(1)->implode(','), 'folder_id' => $publicId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "bookmarks.1" => ["The bookmarks.1 attribute is invalid"],
            ]);

        $this->hideFolderResponse(['bookmarks' => $bookmarkPublicIds->take(3)->add($bookmarkPublicIds[0])->implode(','), 'folder_id' => $publicId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
                "bookmarks.3" => ["The bookmarks.3 field has a duplicate value."]
            ]);

        $this->hideFolderResponse(['bookmarks' => $bookmarkPublicIds->implode(','), 'folder_id' => $publicId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => ['The bookmarks must not have more than 50 items.']
            ]);
    }

    public function testHideFolderBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();
        $bookmarkIds = $bookmarks->pluck('id')->all();
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarkIds);

        $this->hideFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'bookmarks' => $bookmarksPublicIds[0],
        ])->assertOk();

        $folderBookmarks = FolderBookmark::where('folder_id', $folder->id)->get();

        $this->assertEquals(
            FolderBookmarkVisibility::from($folderBookmarks->where('bookmark_id', $bookmarkIds[0])->sole()->visibility),
            FolderBookmarkVisibility::PRIVATE
        );

        $this->assertEquals(
            FolderBookmarkVisibility::from($folderBookmarks->where('bookmark_id', $bookmarkIds[1])->sole()->visibility),
            FolderBookmarkVisibility::PUBLIC
        );
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->hideFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'bookmarks' => $this->generateBookmarkId()->present(),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->hideFolderResponse([
            'folder_id' => $this->generateFolderId()->present(),
            'bookmarks' => $this->generateBookmarkId()->present(),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenBookmarksDoesNotExistsInFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects(BookmarkFactory::new()->count(5)->for($user)->create())->present();

        $folder = FolderFactory::new()->for($user)->create();

        $this->hideFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'bookmarks' => $bookmarksPublicIds->add($this->generateBookmarkId()->present())->implode(','),
        ])->assertNotFound()
            ->assertExactJson(['message' => "BookmarkNotFound"]);

        //when no bookmark exists in folder
        $this->hideFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'bookmarks' => $this->generateBookmarkId()->present(),
        ])->assertNotFound()
            ->assertExactJson(['message' => "BookmarkNotFound"]);
    }

    public function testWillReturnNotFoundWhenBookmarkHasBeenDeleted(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::times(2)->for($user)->create();
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarks->pluck('id')->all());

        $bookmarks->first()->delete();

        $this->hideFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'bookmarks' => $bookmarksPublicIds->implode(','),
        ])->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnForbiddenWhenBookmarksWhereNotAddedByFolderOwner(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmark->id);

        $this->hideFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'bookmarks' => $bookmark->public_id->present(),
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotHideCollaboratorBookmarks']);
    }
}
