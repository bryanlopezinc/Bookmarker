<?php

namespace Tests\Feature\Folder;

use App\Models\FolderBookmark;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;

class FetchPublicFolderBookmarksTest extends TestCase
{
    use WillCheckBookmarksHealth;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('viewPublicfolderBookmarks', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders/public/bookmarks', 'viewPublicfolderBookmarks');
    }

    public function testUnAuthorizedClientCannotAccessRoute(): void
    {
        $this->getTestResponse(['folder_id' => 400])->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse()->assertJsonValidationErrors(['folder_id']);
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->getTestResponse(['per_page' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);

        $this->getTestResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->getTestResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);
    }

    public function testFetchFolderBookmarks(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $user = UserFactory::new()->create();

        $privateFolderBookmarkIDs = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');
        $publicFolderBookmarkIDs = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');
        $folder = FolderFactory::new()->public()->create(['user_id' => $user->id]);

        $publicFolderBookmarkIDs->tap(fn (Collection $collection) => FolderBookmark::insert(
            $collection->map(fn (int $bookmarkID) => [
                'folder_id' => $folder->id,
                'bookmark_id' => $bookmarkID,
                'is_public' => true
            ])->all()
        ));

        $privateFolderBookmarkIDs->tap(fn (Collection $collection) => FolderBookmark::insert(
            $collection->map(fn (int $bookmarkID) => [
                'folder_id' => $folder->id,
                'bookmark_id' => $bookmarkID,
                'is_public' => false
            ])->all()
        ));

        $this->getTestResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson(function (AssertableJson $json) use ($publicFolderBookmarkIDs, $folder) {
                $json->etc()
                    ->where('links.first', route('viewPublicfolderBookmarks', ['per_page' => 15, 'folder_id' => $folder->id, 'page' => 1]))
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) use ($publicFolderBookmarkIDs) {
                        $json->etc()
                            ->where('type', 'folderBookmark')
                            ->where('attributes.id', function (int $bookmarkID) use ($json, $publicFolderBookmarkIDs) {
                                $json->where('attributes.is_public', true);

                                return $publicFolderBookmarkIDs->containsStrict($bookmarkID);
                            });

                        (new AssertableJsonString($json->toArray()))
                            ->assertCount(15, 'attributes')
                            ->assertCount(3, 'attributes.created_on')
                            ->assertStructure([
                                'type',
                                'attributes' => [
                                    'id',
                                    'title',
                                    'web_page_link',
                                    'has_preview_image',
                                    'preview_image_url',
                                    'description',
                                    'has_description',
                                    'source',
                                    'tags',
                                    'has_tags',
                                    'tags_count',
                                    'is_healthy',
                                    'is_user_favourite',
                                    'is_public',
                                    'created_on' => [
                                        'date_readable',
                                        'date_time',
                                        'date',
                                    ]
                                ]
                            ]);
                    });
            })
            ->assertJsonStructure([
                "links" => [
                    "first",
                    "prev",
                ],
                "meta" => [
                    "current_page",
                    "path",
                    "per_page",
                    "has_more_pages",
                ]
            ]);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $user = UserFactory::new()->create();

        $folderBookmarkIDs = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');
        $folder = FolderFactory::new()->public()->create(['user_id' => $user->id]);

        $folderBookmarkIDs->tap(fn (Collection $collection) => FolderBookmark::insert(
            $collection->map(fn (int $bookmarkID) => [
                'folder_id' => $folder->id,
                'bookmark_id' => $bookmarkID,
                'is_public' => true
            ])->all()
        ));

        $this->getTestResponse(['folder_id' => $folder->id])->assertOk();

        $this->assertBookmarksHealthWillBeChecked($folderBookmarkIDs->all());
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $folder = FolderFactory::new()->create([]);

        $this->getTestResponse(['folder_id' => $folder->id + 1])->assertNotFound();
    }

    public function testWillReturnNotFoundWhenFolderIsPrivate(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $folder = FolderFactory::new()->create([]);

        $this->getTestResponse(['folder_id' => $folder->id])->assertNotFound();
    }

    public function testWillReturnEmptyJsonWhenFolderHasNoItems(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $folder = FolderFactory::new()->public()->create([]);

        $this->getTestResponse(['folder_id' => $folder->id])->assertJsonCount(0, 'data')->assertOk();
    }
}
