<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AddBookmarksToFolderTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('addBookmarksToFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks/folders', 'addBookmarksToFolder');
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
                "folder" => [
                    "The folder field is required."
                ],
                "bookmarks.1" => [
                    "The bookmarks.1 attribute is invalid"
                ]
            ]);
    }

    public function testCannotAddMoreThan_30_bookmarks_simultaneouly(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['bookmarks' => implode(',', range(1, 31))])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => 'The bookmarks must not have more than 30 items.'
            ]);
    }

    public function testWillAddBookmarksToFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id
        ]);

        $folderID = FolderFactory::new()->create([
            'user_id' => $user->id,
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ])->id;

        $this->getTestResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folderID
        ])->assertCreated();

        $bookmarks->pluck('id')->each(function (int $bookmarkID) use ($folderID) {
            $this->assertDatabaseHas(FolderBookmark::class, [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folderID
            ]);
        });

        $this->assertDatabaseHas(FolderBookmarksCount::class, [
            'folder_id' => $folderID,
            'count' => 10,
        ]);

        //Assert the folder updated_at column was updated
        $this->assertTrue(
            Folder::query()->whereKey($folderID)->first('updated_at')->updated_at->isToday()
        );
    }

    public function testCannotAddBookmarkToFolderMoreThanOnce(): void
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
            'folder' => $folder->id
        ])->assertCreated();

        $this->getTestResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertStatus(Response::HTTP_CONFLICT);
    }

    public function testUserCanOnlyAddBookmarksToOwnFolder(): void
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

    public function testUserCanOnlyAddOwnBookmarksToOwnFolder(): void
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

    public function testUserCannotAddInvalidBookmarksToFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->create([
            'user_id' => $user->id
        ]);

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'bookmarks' => $bookmarks->pluck('id')->map(fn (int $bookmarkID) => $bookmarkID + 1)->implode(','),
            'folder' => $folder->id
        ])
            ->assertNotFound()
            ->assertExactJson([
                'message' => "The bookmarks does not exists"
            ]);
    }

    public function testUserCannotAddBookmarksToInvalidFolder(): void
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
