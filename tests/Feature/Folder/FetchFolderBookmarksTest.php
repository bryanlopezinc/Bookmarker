<?php

namespace Tests\Feature\Folder;

use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\FolderPermission;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderAccessFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;

class FetchFolderBookmarksTest extends TestCase
{
    use WillCheckBookmarksHealth;

    protected function folderBookmarksResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('folderBookmarks', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/bookmarks', 'folderBookmarks');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->folderBookmarksResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->folderBookmarksResponse()->assertJsonValidationErrors(['folder_id']);
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->folderBookmarksResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->folderBookmarksResponse(['per_page' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);

        $this->folderBookmarksResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->folderBookmarksResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);
    }

    public function testFetchFolderBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        //user bookmarks not in folder
        BookmarkFactory::new()->count(5)->create(['user_id' => $user->id]);

        $bookmarkShouldBePublicFn = fn (int $bookmarkID) => $bookmarkID % 2 === 0;
        $bookmarkIDs = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');

        $folder = FolderFactory::new()->afterCreating(fn (Folder $folder) => FolderBookmark::insert(
            $bookmarkIDs->map(fn (int $bookmarkID) => [
                'folder_id' => $folder->id,
                'bookmark_id' => $bookmarkID,
                'is_public' => $bookmarkShouldBePublicFn($bookmarkID)
            ])->all()
        ))->create([
            'user_id' => $user->id
        ]);

        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
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
                                    'source',
                                    'tags',
                                    'has_tags',
                                    'tags_count',
                                    'is_healthy',
                                    'is_user_favorite',
                                    'can_favorite',
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

    public function testUserWithAnyPermissionCanViewBookmarks(): void
    {
        $this->assertUserWithPermissionCanPerformAction(function (FolderAccessFactory $factory) {
            return $factory->addBookmarksPermission();
        });

        $this->assertUserWithPermissionCanPerformAction(function (FolderAccessFactory $factory) {
            return $factory->viewBookmarksPermission();
        });

        $this->assertUserWithPermissionCanPerformAction(function (FolderAccessFactory $factory) {
            return $factory->removeBookmarksPermission();
        });

        $this->assertUserWithPermissionCanPerformAction(function (FolderAccessFactory $factory) {
            return $factory->inviteUser();
        });

        $this->assertUserWithPermissionCanPerformAction(function (FolderAccessFactory $factory) {
            return $factory->updateFolderPermission();
        });
    }

    private function assertUserWithPermissionCanPerformAction(\Closure $permission): void
    {
        [$user, $folderOwner] = UserFactory::new()->count(2)->create();

        Passport::actingAs($user);

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        /** @var FolderAccessFactory */
        $factory = $permission(FolderAccessFactory::new()->user($user->id)->folder($folder->id));

        $factory = $factory->create();

        try {
            $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk();
        } catch (\Throwable $e) {
            $message = sprintf(
                '********* Failed asserting that user with permission [%s] can view folder bookmarks ******* ',
                FolderPermission::query()->whereKey($factory->permission_id)->sole(['name'])->name
            );

            $this->appendMessageToException($message, $e);

            throw $e;
        }

        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk();
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

        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertOk();

        $this->assertBookmarksHealthWillBeChecked($bookmarkIDs->all());
    }

    public function testFolderMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->folderBookmarksResponse([
            'folder_id' => $folder->id
        ])->assertForbidden();
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->folderBookmarksResponse([
            'folder_id' => $folder->id + 1
        ])->assertNotFound();
    }

    public function testWillReturnEmptyJsonWhenFolderHasNoItems(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->folderBookmarksResponse(['folder_id' => $folder->id])->assertJsonCount(0, 'data')->assertOk();
    }

    public function test_user_with_permission_cannot_view_bookmarks_when_folder_owner_has_deleted_account(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->viewBookmarksPermission()
            ->create();

        Passport::actingAs($folderOwner);
        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();

        Passport::actingAs($collaborator);
        $this->folderBookmarksResponse([
            'folder_id' => $folder->id,
        ])->assertNotFound()
            ->assertExactJson(['message' => "The folder does not exists"]);
    }

    public function test_isUserFavorite_willBeTrueForOnlyBookmarkOwners(): void
    {
        [$user, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);
        $userBookmarks = BookmarkFactory::new()->count(3)->create(['user_id' => $user->id])->pluck('id');
        $folderOwnerBookmarks = BookmarkFactory::new()->count(5)->create(['user_id' => $folderOwner->id])->pluck('id');

        // ****************** user adds bookmarks to favorites and folder actions ****************
        Passport::actingAs($user);
        FolderAccessFactory::new()
            ->user($user->id)
            ->folder($folder->id)
            ->addBookmarksPermission()
            ->create();

        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $userBookmarks->implode(','),
            'folder' => $folder->id
        ])->assertCreated();

        $this->postJson(route('createFavorite'), [
            'bookmarks' => $userBookmarks->implode(',')
        ])->assertCreated();

        // ****************** folder owner adds bookmarks to favorites and folder actions ****************
        Passport::actingAs($folderOwner);
        $this->postJson(route('createFavorite'), [
            'bookmarks' => $folderOwnerBookmarks->implode(',')
        ])->assertCreated();

        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $folderOwnerBookmarks->implode(','),
            'folder' => $folder->id
        ])->assertCreated();

        //folder owner fetches folder bookmarks
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->collect('data')
            ->each(function (array $data) use ($folderOwnerBookmarks) {
                $assertableJson = AssertableJson::fromArray($data);

                if ($folderOwnerBookmarks->contains($data['attributes']['id'])) {
                    $assertableJson->where('attributes.is_user_favorite', true);
                    return;
                }

                $assertableJson->where('attributes.is_user_favorite', false);
            });

        //user fetches folder bookmarks
        Passport::actingAs($user);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->collect('data')
            ->each(function (array $data) use ($folderOwnerBookmarks) {
                $assertableJson = AssertableJson::fromArray($data);

                if ($folderOwnerBookmarks->contains($data['attributes']['id'])) {
                    $assertableJson->where('attributes.is_user_favorite', false);
                    return;
                }

                $assertableJson->where('attributes.is_user_favorite', true);
            });
    }

    public function testCanAddBookmarkToFavorites_willBeTrue_whenUserOwnsBookmarks_andBookmarkDoesNotExistInFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->create(['user_id' => $user->id]);
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertCreated();

        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->collect('data')
            ->each(function (array $data) {
                $this->assertTrue($data['attributes']['can_favorite']);
            });
    }

    public function testCanAddBookmarkToFavorites_willBeFalse_whenUserOwnsBookmarks_andBookmarkExistsInFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->create(['user_id' => $user->id]);
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertCreated();

        $this->postJson(route('createFavorite'), [
            'bookmarks' => $bookmarks->pluck('id')->implode(',')
        ])->assertCreated();

        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->collect('data')
            ->each(function (array $data) {
                $this->assertFalse($data['attributes']['can_favorite']);
            });
    }

    public function testCanAddBookmarkToFavorites_willBeFalse_whenUserDoesNotOwnsBookmark(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(3)->create(['user_id' => $folderOwner->id]);
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Passport::actingAs($folderOwner);
        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertCreated();

        FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($collaborator);
        $this->folderBookmarksResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->collect('data')
            ->each(function (array $data) {
                $this->assertFalse($data['attributes']['can_favorite']);
            });
    }
}
