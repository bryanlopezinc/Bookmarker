<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\FolderBookmark;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\AssertsBookmarksWillBeHealthchecked;

class FetchFolderBookmarksTest extends TestCase
{
    use AssertsBookmarksWillBeHealthchecked;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('folderBookmarks', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders/bookmarks', 'folderBookmarks');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrors(['folder_id']);
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

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
        Passport::actingAs($user = UserFactory::new()->create());

        //user bookmarks not in folder
        BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id
        ]);

        $bookmarkShouldBePublicFn = fn (int $bookmarkID) => $bookmarkID % 2 === 0;

        $bookmarkIDs = BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id
        ])->pluck('id');

        $folder = FolderFactory::new()->afterCreating(fn (Folder $folder) => FolderBookmark::insert(
            $bookmarkIDs->map(fn (int $bookmarkID) => [
                'folder_id' => $folder->id,
                'bookmark_id' => $bookmarkID,
                'is_public' => $bookmarkShouldBePublicFn($bookmarkID)
            ])->all()
        ))->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'folder_id' => $folder->id
        ])
            ->assertSuccessful()
            ->assertJsonCount(5, 'data')
            ->assertJson(function (AssertableJson $json) use ($bookmarkIDs, $folder, $bookmarkShouldBePublicFn) {
                $json->etc()
                    ->where('links.first', route('folderBookmarks', ['per_page' => 15, 'folder_id' => $folder->id, 'page' => 1]))
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) use ($bookmarkIDs, $bookmarkShouldBePublicFn) {
                        $json->etc()
                            ->where('type', 'folderBookmark')
                            ->where('attributes.id', function (int $bookmarkID) use ($json, $bookmarkIDs, $bookmarkShouldBePublicFn) {
                                $json->where('attributes.is_public', $bookmarkShouldBePublicFn($bookmarkID));

                                return $bookmarkIDs->containsStrict($bookmarkID);
                            });

                        (new AssertableJsonString($json->toArray()))
                            ->assertCount(16, 'attributes')
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
                                    'site_id',
                                    'from_site',
                                    'tags',
                                    'has_tags',
                                    'tags_count',
                                    'is_dead_link',
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
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');

        $folder = FolderFactory::new()->afterCreating(fn (Folder $folder) => FolderBookmark::insert(
            $bookmarkIDs->map(fn (int $bookmarkID) => [
                'folder_id' => $folder->id,
                'bookmark_id' => $bookmarkID,
                'is_public' => false
            ])->all()
        ))->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['folder_id' => $folder->id])->assertOk();

        $this->assertBookmarksHealthWillBeChecked($bookmarkIDs->all());
    }

    public function testFolderMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->getTestResponse([
            'folder_id' => $folder->id
        ])->assertForbidden();
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'folder_id' => $folder->id + 1
        ])->assertNotFound();
    }

    public function testWillReturnEmptyJsonWhenFolderHasNoItems(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['folder_id' => $folder->id])->assertJsonCount(0, 'data')->assertSuccessful();
    }
}
