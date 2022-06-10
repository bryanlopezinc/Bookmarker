<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use App\Models\UserBookmarksCount;
use App\Models\UserFoldersCount;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\CreatesBookmark;

class DeleteFolderTest extends TestCase
{
    use WithFaker, CreatesBookmark;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders', 'createFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrors(['folder']);
    }

    public function testDeleteFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderIDs = FolderFactory::new()->count(3)->create(['user_id' => $user->id])->pluck('id');

        //Save 3 bookmarks for user
        for ($i = 0; $i < 3; $i++)  $this->saveBookmark();

        $userBookmarks = Bookmark::query()->where('user_id', $user->id)->get();

        UserFoldersCount::create([
            'count' => 3,
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['folder' => $folderIDs->last()])->assertOk();

        $folderIDs->take(2)->each(function (int $folderID) {
            $this->assertDatabaseHas(Folder::class, ['id' => $folderID]);
        });

        $this->assertDatabaseMissing(Folder::class, ['id' => $folderIDs->last()]);

        $this->assertDatabaseMissing(FolderBookmarksCount::class, [
            'folder_id' => $folderIDs->last(),
        ]);

        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $user->id,
            'count' => 2,
            'type' => UserFoldersCount::TYPE
        ]);

        $userBookmarks->each(function (Bookmark $bookmark) {
            //Assert folder contents was not deleted
            $this->assertDatabaseHas(Bookmark::class, ['id' => $bookmark->id,]);
        });
    }

    public function testDeleteFolderAndItsItems(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderID = FolderFactory::new()->create(['user_id' => $user->id])->id;

        UserFoldersCount::create([
            'count' => 1,
            'user_id' => $user->id
        ]);

        //Save 3 bookmarks for user
        for ($i = 0; $i < 3; $i++)  $this->saveBookmark();

        $userBookmarks =  Bookmark::query()
            ->where('user_id', $user->id)
            ->get()
            // add user bookmarks to a folder.
            ->tap(fn (Collection $bookmarks) => $this->postJson(route('addBookmarksToFolder'), [
                'bookmarks' => $bookmarks->pluck('id')->implode(','),
                'folder' => $folderID
            ])->assertCreated());

        //delete folder and of its contents
        $this->getTestResponse([
            'folder' => $folderID,
            'delete_bookmarks' => true
        ])->assertOk();

        $this->assertDatabaseMissing(Folder::class, ['id' => $folderID]);
        $this->assertDatabaseMissing(FolderBookmarksCount::class, ['folder_id' => $folderID]);

        //Assert user bookmarks count was decremented
        $this->assertDatabaseHas(UserBookmarksCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserBookmarksCount::TYPE
        ]);

        //Assert user folders count was decremented
        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserFoldersCount::TYPE
        ]);

        $userBookmarks->each(function (Bookmark $bookmark) use ($folderID) {
            //Assert folder bookmarks where deleted
            $this->assertDatabaseMissing(FolderBookmark::class, [
                'bookmark_id' => $bookmark->id,
                'folder_id' => $folderID
            ]);

            //Assert bookmark was deleted
            $this->assertDatabaseMissing(Bookmark::class, ['id' => $bookmark->id,]);
        });
    }

    public function testCanOnlyDeleteOwnFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->getTestResponse(['folder' => $folderID])->assertForbidden();
    }

    public function testCannotDeleteInvalidFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->getTestResponse(['folder' => $folderID + 1])
            ->assertNotFound()
            ->assertExactJson([
                'message' => "The folder does not exists"
            ]);;
    }
}
