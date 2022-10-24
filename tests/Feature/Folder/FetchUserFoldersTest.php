<?php

namespace Tests\Feature\Folder;

use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserFoldersTest extends TestCase
{
    use WithFaker;

    protected function userFoldersResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('userFolders', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders', 'userFolders');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->userFoldersResponse()->assertUnauthorized();
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->userFoldersResponse(['per_page', 'page'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page field must have a value.'],
                'page' => ['The page field must have a value.'],
            ]);

        $this->userFoldersResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->userFoldersResponse(['per_page' => 40])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);

        $this->userFoldersResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->userFoldersResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);
    }

    public function testFetchUserFolders(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        FolderFactory::new()->count(10)->create(); //folders does not belong to current user.

        $userFolders = FolderFactory::new()->count(10)->create([
            'user_id' => $user->id,
            'name' => "<script>alert(Cross Site Scripting)</script>",
            'description' => "<script>alert(CSS)</script>",
        ]);

        $this->userFoldersResponse([])
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson(function (AssertableJson $json) use ($userFolders) {
                $json
                    ->etc()
                    ->where('links.first', $link = route('userFolders', ['per_page' => 15, 'page' => 1]))
                    ->where('links.prev', $link)
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) use ($userFolders) {
                        $json->etc();
                        $json->where('attributes.id', fn (int $id) => $userFolders->pluck('id')->containsStrict($id));
                        $json->where('attributes.is_public', false);
                        $json->where('attributes.storage.capacity', 200);
                        $json->where('attributes.storage.is_full', false);
                        $json->where('attributes.storage.available', 200);
                        $json->where('attributes.storage.percentage_used', 0);
                        $json->where('attributes.tags', []);
                        $json->where('attributes.has_tags', false);
                        $json->where('attributes.tags_count', 0);
                        $json->where('attributes.has_description', true);

                        //Assert the name  and description response sent to client are sanitized
                        $json->where('attributes.name', '&lt;script&gt;alert(Cross Site Scripting)&lt;/script&gt;');
                        $json->where('attributes.description', '&lt;script&gt;alert(CSS)&lt;/script&gt;');

                        (new AssertableJsonString($json->toArray()))
                            ->assertCount(2)
                            ->assertCount(11, 'attributes')
                            ->assertCount(5, 'attributes.storage')
                            ->assertStructure([
                                "type",
                                "attributes" => [
                                    "id",
                                    "name",
                                    "description",
                                    "has_description",
                                    "date_created",
                                    "last_updated",
                                    "is_public",
                                    'tags',
                                    'has_tags',
                                    'tags_count',
                                    'storage' => [
                                        'items_count',
                                        'capacity',
                                        'is_full',
                                        'available',
                                        'percentage_used'
                                    ]
                                ]
                            ]);
                    });
            })
            ->assertJsonCount(2, 'links')
            ->assertJsonCount(4, 'meta')
            ->assertJsonStructure([
                'data',
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

        $this->userFoldersResponse(['per_page' => 20])
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->where('links.first', route('userFolders', ['per_page' => 20, 'page' => 1]))->etc();
            });
    }

    public function testWillReturnRecentFoldersByDefault(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->count(5)->create(['user_id' => $user->id]);

        $response = $this->userFoldersResponse([])->assertOk();

        $this->assertEquals(
            $folders->pluck('id')->sortDesc()->values()->all(),
            collect($response->json('data'))->pluck('attributes.id')->all()
        );
    }

    public function testWillSortFoldersByNewest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->count(5)->create(['user_id' => $user->id]);

        $response = $this->userFoldersResponse(['sort' => 'newest'])->assertOk();

        $this->assertEquals(
            $folders->pluck('id')->sortDesc()->values()->all(),
            collect($response->json('data'))->pluck('attributes.id')->all()
        );
    }

    public function testWillSortFoldersByOldest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->count(5)->create(['user_id' => $user->id]);

        $response = $this->userFoldersResponse(['sort' => 'oldest'])->assertOk();

        $this->assertEquals(
            $folders->pluck('id')->all(),
            collect($response->json('data'))->pluck('attributes.id')->all()
        );
    }

    public function testWillSortFoldersByMostItems(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userFolders = FolderFactory::new()->count(7)->create(['user_id' => $user->id]);

        $folderWithMostItems = $userFolders->random();

        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => BookmarkFactory::new()->count(3)->create(['user_id' => $user->id])->pluck('id')->implode(','),
            'folder' => $folderWithMostItems->id
        ])->assertCreated();

        $response = $this->userFoldersResponse(['sort' => 'most_items'])->assertOk();

        $this->assertEquals(
            $folderWithMostItems->id,
            collect($response->json('data'))->pluck('attributes.id')->first()
        );
    }

    public function testWillSortFoldersByLeastItems(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userFoldersIDs = FolderFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');

        $folderIDWithLeastItems = $userFoldersIDs->random();
        $bookmarksAmountToAddToFolder = 1;

        $userFoldersIDs
            ->reject($folderIDWithLeastItems)
            ->each(function (int $folderID) use (&$bookmarksAmountToAddToFolder, $user) {
                $this->postJson(route('addBookmarksToFolder'), [
                    'bookmarks' => BookmarkFactory::new()->count($bookmarksAmountToAddToFolder)->create(['user_id' => $user->id])->pluck('id')->implode(','),
                    'folder' => $folderID
                ])->assertCreated();

                $bookmarksAmountToAddToFolder++;
            });

        $response = $this->userFoldersResponse(['sort' => 'least_items'])->assertOk();

        $this->assertEquals(
            [$folderIDWithLeastItems, ...$userFoldersIDs->reject($folderIDWithLeastItems)->all()],
            collect($response->json('data'))->pluck('attributes.id')->all()
        );
    }

    public function testWillSortFoldersByRecentlyUpdated(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userFolders = FolderFactory::new()->count(5)->create(['user_id' => $user->id]);

        $recentlyUpdatedFolder = $userFolders->random();

        $this->travel(5)->minutes(fn () => $recentlyUpdatedFolder->update([
            'name' => 'Links to all my billions'
        ]));

        $response = $this->userFoldersResponse(['sort' => 'updated_recently'])->assertOk();

        $this->assertEquals(
            $recentlyUpdatedFolder->id,
            collect($response->json('data'))->pluck('attributes.id')->first()
        );
    }

    public function testIsPublicAttributeWillBeTrue(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        FolderFactory::new()->count(5)->public()->create([
            'user_id' => $user->id,
        ]);

        $this->userFoldersResponse([])
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson(function (AssertableJson $json) {
                $json
                    ->etc()
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) {
                        $json->etc()->where('attributes.is_public', true);
                    });
            });
    }

    public function testPaginateResponse(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        FolderFactory::new()->count(20)->create([
            'user_id' => $user->id
        ]);

        $this->userFoldersResponse(['per_page' => 17])
            ->assertOk()
            ->assertJsonCount(17, 'data');
    }

    public function testWillFetchFolderTags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $tags = TagFactory::new()->count(5)->make()->pluck('name');

        $this->postJson(route('createFolder'), [
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'tags' => $tags->implode(',')
        ])->assertCreated();

        $this->userFoldersResponse([])
            ->assertOk()
            ->assertJsonCount(5, 'data.0.attributes.tags')
            ->assertJson(function (AssertableJson $json) use ($tags) {
                $json->where('data.0.attributes.tags', function (Collection $folderTags) use ($tags) {
                    $this->assertEquals($tags->sortDesc()->values(), $folderTags->sortDesc()->values());

                    return true;
                })->etc();
            });
    }
}
