<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RemoveBookmarksFromFolderTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('removeBookmarksFromFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks/folders', 'removeBookmarksFromFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks', 'folder']);
    }

    public function testWillThrowValidationWhenAttrbutesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['bookmarks' => '1,2bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "folder" => ["The folder field is required."],
                "bookmarks.1" => ["The bookmarks.1 attribute is invalid"]
            ]);
    }

    public function testAttributesMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'bookmarks' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);
    }

    public function testCannotRemoveMoreThan_30_bookmarks_simultaneously(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['bookmarks' => implode(',', range(1, 31))])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => 'The bookmarks must not have more than 30 items.'
            ]);
    }

    public function testWillRemoveBookmarksFromFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id,
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ])->pluck('id');

        $folderID = FolderFactory::new()->create([
            'user_id' => $user->id
        ])->id;

        //add bookmarks to folder.
        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertCreated();

        $bookmarksToRemove = $bookmarkIDs->take(9);

        //Remove first 9 bookmarks from folder.
        $this->getTestResponse([
            'bookmarks' => $bookmarksToRemove->implode(','),
            'folder' => $folderID
        ])->assertSuccessful();

        $bookmarksToRemove->each(function (int $bookmarkID) use ($folderID) {
            $this->assertDatabaseMissing(FolderBookmark::class, [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folderID
            ]);
        });

        //Ensure last bookmark still exists in folder
        $this->assertDatabaseHas(FolderBookmark::class, [
            'bookmark_id' => $bookmarkIDs->last(),
            'folder_id' => $folderID
        ]);

        $this->assertDatabaseHas(FolderBookmarksCount::class, [
            'folder_id' => $folderID,
            'count' => 1,
        ]);

        //Assert the folder updated_at column was updated
        $this->assertTrue(
            Folder::query()->whereKey($folderID)->first('updated_at')->updated_at->isToday()
        );
    }

    public function testWillReturnNotFoundResponseWhenBookmarksDontExistsInFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(3)->create([
            'user_id' => $user->id
        ])->pluck('id');

        $folderID = FolderFactory::new()->create([
            'user_id' => $user->id
        ])->id;

        //Assert will return not found when all bookmarks don't exist in folder
        $this->getTestResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "Bookmarks does not exists in folder"
            ]);

        //add some bookmarks to folder.
        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $bookmarkIDs->take(1)->implode(','),
            'folder' => $folderID
        ])->assertCreated();

        //Assert will return not found when some (but not all) bookmarks exist in folder
        $this->getTestResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "Bookmarks does not exists in folder"
            ]);
    }

    public function testCannotRemoveInvalidBookmarksFromFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(3)->create([
            'user_id' => $user->id
        ])->pluck('id');

        $folderID = FolderFactory::new()->create([
            'user_id' => $user->id
        ])->id;

        //Assert will return not found when all bookmarks dont exists
        $this->getTestResponse([
            'bookmarks' => $bookmarkIDs->map(fn (int $id) => $id + 1)->implode(','),
            'folder' => $folderID
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "The bookmarks does not exists"
            ]);

        //Assert will return not found when some (but not all) bookmarks exists
        $this->getTestResponse([
            'bookmarks' => $bookmarkIDs->push($bookmarkIDs->last() + 1)->implode(','),
            'folder' => $folderID
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "The bookmarks does not exists"
            ]);
    }

    public function testUserCanOnlyRemoveBookmarksFromOwnFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id
        ]);

        $folder = FolderFactory::new()->create([
            'user_id' => UserFactory::new()->create()->id //Another user's folder
        ]);

        $this->getTestResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertForbidden();
    }

    public function testUserCanOnlyRemoveOwnBookmarksFromOwnFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(10)->create([
            'user_id' => UserFactory::new()->create()->id //Another user's bookmarks
        ]);

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertForbidden();
    }

    public function testUserCannotRemoveBookmarksFromInvalidFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->create([
            'user_id' => $user->id
        ]);

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "The folder does not exists"
            ]);
    }
}
