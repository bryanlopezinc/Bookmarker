<?php

namespace Tests\Feature;

use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserFoldersTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('userFolders', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/users/folders', 'userFolders');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['per_page', 'page'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page field must have a value.'],
                'page' => ['The page field must have a value.'],
            ]);

        $this->getTestResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->getTestResponse(['per_page' => 40])
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

    public function testFetchUserFolders(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        FolderFactory::new()->count(10)->create(); //folders does not belong to current user.

        $userfolders = FolderFactory::new()->count(10)->create([
            'user_id' => $user->id,
            'name' => "<script>alert(Cross Site Scripting)</script>",
            'description' => "<script>alert(CSS)</script>",
        ]);

        $this->getTestResponse([])
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson(function (AssertableJson $json) use ($userfolders) {
                $json
                    ->etc()
                    ->where('links.first', route('userFolders', ['per_page' => 15, 'page' => 1]))
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) use ($userfolders) {
                        $json->etc();
                        $json->where('attributes.id', fn (int $id) => $userfolders->pluck('id')->containsStrict($id));
                        $json->where('attributes.is_public', false);

                        //Assert the name  and decription response sent to client are sanitized
                        $json->where('attributes.name', '&lt;script&gt;alert(Cross Site Scripting)&lt;/script&gt;');
                        $json->where('attributes.description', '&lt;script&gt;alert(CSS)&lt;/script&gt;');

                        (new AssertableJsonString($json->toArray()))
                            ->assertCount(2)
                            ->assertCount(7, 'attributes')
                            ->assertStructure([
                                "type",
                                "attributes" => [
                                    "id",
                                    "name",
                                    "description",
                                    "date_created",
                                    "last_updated",
                                    "items_count",
                                    "is_public"
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

        $this->getTestResponse(['per_page' => 20])
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->where('links.first', route('userFolders', ['per_page' => 20, 'page' => 1]))->etc();
            });
    }

    public function testWillReturnRecentFoldersByDefault(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->count(5)->create(['user_id' => $user->id]);

        $response = $this->getTestResponse([])->assertOk();

        $this->assertEquals(
            $folders->pluck('id')->sortDesc()->values()->all(),
            collect($response->json('data'))->pluck('attributes.id')->all()
        );
    }

    public function testWillSortFoldersByNewest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->count(5)->create(['user_id' => $user->id]);

        $response = $this->getTestResponse(['sort' => 'newest'])->assertOk();

        $this->assertEquals(
            $folders->pluck('id')->sortDesc()->values()->all(),
            collect($response->json('data'))->pluck('attributes.id')->all()
        );
    }

    public function testWillSortFoldersByOldest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->count(5)->create(['user_id' => $user->id]);

        $response = $this->getTestResponse(['sort' => 'oldest'])->assertOk();

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

        $response = $this->getTestResponse(['sort' => 'most_items'])->assertOk();

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

        $response = $this->getTestResponse(['sort' => 'least_items'])->assertOk();

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

        $response = $this->getTestResponse(['sort' => 'updated_recently'])->assertOk();

        $this->assertEquals(
            $recentlyUpdatedFolder->id,
            collect($response->json('data'))->pluck('attributes.id')->first()
        );
    }

    public function testIsPublicAttributeWillBetrue(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        FolderFactory::new()->count(5)->public()->create([
            'user_id' => $user->id,
        ]);

        $this->getTestResponse([])
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

        $this->getTestResponse(['per_page' => 17])
            ->assertOk()
            ->assertJsonCount(17, 'data');
    }
}
