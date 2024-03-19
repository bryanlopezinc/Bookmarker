<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\CreateFolderBookmarks;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;

class FetchUserFoldersTest extends TestCase
{
    use WithFaker;
    use AssertValidPaginationData;

    private CreateFolderBookmarks $addBookmarksToFolder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addBookmarksToFolder = new CreateFolderBookmarks();
    }

    protected function userFoldersResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('userFolders', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders', 'userFolders');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->userFoldersResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'userFolders');

        $this->userFoldersResponse(['sort' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function testFetchUserFolders(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        FolderFactory::new()->create(); //folders does not belong to current user.

        $folder = FolderFactory::new()->for($user)->create();

        $this->userFoldersResponse([])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(9, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.id', $folder->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "name",
                            "description",
                            "has_description",
                            "date_created",
                            "last_updated",
                            "visibility",
                            'collaborators_count',
                            'storage' => [
                                'items_count',
                                'capacity',
                                'is_full',
                                'available',
                                'percentage_used'
                            ]
                        ]
                    ]
                ]
            ])
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
    }

    public function testWillReturnRecentFoldersByDefault(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->for($user)->count(2)->create();

        $this->userFoldersResponse([])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $folders[1]->id)
            ->assertJsonPath('data.1.attributes.id', $folders[0]->id);
    }

    public function testSortByLatest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->for($user)->count(2)->create();

        $this->userFoldersResponse(['sort' => 'newest'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $folders[1]->id)
            ->assertJsonPath('data.1.attributes.id', $folders[0]->id);
    }

    public function testSortByOldest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->for($user)->count(2)->create();

        $this->userFoldersResponse(['sort' => 'oldest'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $folders[0]->id)
            ->assertJsonPath('data.1.attributes.id', $folders[1]->id);
    }

    public function testSortByMostItems(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->count(3)->for($user)->create();

        $this->addBookmarksToFolder->create($folders[1]->id, BookmarkFactory::new()->create()->id);

        $this->userFoldersResponse(['sort' => 'most_items'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.id', $folders[1]->id);
    }

    public function testSortByLeastItems(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->count(3)->for($user)->create();

        $this->addBookmarksToFolder->create($folders[1]->id, BookmarkFactory::times(3)->create()->pluck('id')->all());
        $this->addBookmarksToFolder->create($folders[0]->id, BookmarkFactory::times(2)->create()->pluck('id')->all());

        $this->userFoldersResponse(['sort' => 'least_items'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.id', $folders[2]->id)
            ->assertJsonPath('data.1.attributes.id', $folders[0]->id)
            ->assertJsonPath('data.2.attributes.id', $folders[1]->id);
    }

    public function testSortByRecentlyUpdated(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folders = FolderFactory::new()->count(3)->for($user)->create();

        $this->travel(5)->minutes(fn () => $folders[1]->update(['name' => $this->faker->word]));

        $this->userFoldersResponse(['sort' => 'updated_recently'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.id', $folders[1]->id);
    }

    public function testVisibility(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        FolderFactory::new()->for($user)->create();
        FolderFactory::new()->for($user)->private()->create();
        FolderFactory::new()->for($user)->visibleToCollaboratorsOnly()->create();

        $this->userFoldersResponse([])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.visibility', 'collaborators')
            ->assertJsonPath('data.1.attributes.visibility', 'private')
            ->assertJsonPath('data.2.attributes.visibility', 'public');
    }

    public function testCanRequestPartialResource(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        FolderFactory::new()->count(3)->for($user)->create();

        $this->userFoldersResponse(['fields' => 'id,name,storage.items_count'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "name",
                            "storage" => [
                                'items_count'
                            ],
                        ]
                    ]
                ]
            ]);
    }

    public function testWillReturnUnprocessableWhenFieldsParameterIsInValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->userFoldersResponse(['fields' => 'id,name,foo,1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.2 is invalid.']
            ]);

        $this->userFoldersResponse(['fields' => '1,2,3,4'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.0 is invalid.']
            ]);

        $this->userFoldersResponse(['fields' => 'id,name,description,description'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => [
                    'The fields.2 field has a duplicate value.',
                ]
            ]);
    }
}
