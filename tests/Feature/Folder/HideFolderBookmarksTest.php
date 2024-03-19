<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\CreateFolderBookmarks;
use App\Enums\FolderBookmarkVisibility;
use App\Models\FolderBookmark;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class HideFolderBookmarksTest extends TestCase
{
    use WithFaker;

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
        $this->hideFolderResponse(['folder_id' => 'foo'])->assertNotFound();
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->hideFolderResponse(['folder_id' => 4])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->hideFolderResponse(['folder_id' => 44])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks']);

        $this->hideFolderResponse(['bookmarks' => '1,1,3,4,5', 'folder_id' => 55])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
                "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
            ]);

        $this->hideFolderResponse(['bookmarks' => collect()->times(51)->implode(','), 'folder_id' => 54])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => ['The bookmarks must not have more than 50 items.']
            ]);
    }

    public function testHideFolderBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIds = BookmarkFactory::new()->count(2)->for($user)->create()->pluck('id')->all();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarkIds);

        $this->hideFolderResponse([
            'folder_id' => $folder->id,
            'bookmarks' => (string) $bookmarkIds[0],
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
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->hideFolderResponse([
            'folder_id' => $folder->id,
            'bookmarks' => collect()->times(30)->implode(','),
        ])->assertNotFound();
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->hideFolderResponse([
            'folder_id' => $folder->id + 1,
            'bookmarks' => collect()->times(30)->implode(','),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenBookmarksDoesNotExistsInFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(5)->for($user)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $this->hideFolderResponse([
            'folder_id' => $folder->id,
            'bookmarks' => $bookmarkIDs->map(fn (int $bookmarkID) => $bookmarkID + 1)->implode(','),
        ])->assertNotFound()
            ->assertExactJson(['message' => "BookmarkNotFound"]);
    }

    public function testWillReturnNotFoundWhenBookmarkHasBeenDeleted(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::times(2)->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarks->pluck('id')->all());

        $bookmarks->first()->delete();

        $this->hideFolderResponse([
            'folder_id' => $folder->id,
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
        ])->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnForbiddenWhenBookmarksWhereNotAddedByFolderOwner(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIds = BookmarkFactory::new()->count(5)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder->create($folder->id, $bookmarkIds->all());

        $this->hideFolderResponse([
            'folder_id' => $folder->id,
            'bookmarks' => $bookmarkIds->implode(','),
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotHideCollaboratorBookmarks']);
    }
}
