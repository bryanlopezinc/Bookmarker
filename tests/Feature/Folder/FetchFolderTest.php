<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\CreateFolderBookmarks;
use App\Models\Folder as Model;
use App\UAC;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\GeneratesId;

class FetchFolderTest extends TestCase
{
    use CreatesCollaboration;
    use GeneratesId;

    protected function fetchFolderResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchFolder', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}', 'fetchFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchFolderResponse(['folder_id' => $this->generateFolderId()->present()])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchFolderResponse(['folder_id' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testFetchFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->create();

        $this->fetchFolderResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(9, 'data.attributes')
            ->assertJson(function (AssertableJson $json) use ($folder) {
                $json->etc();
                $json->where('data.attributes.id', $folder->public_id->present());
                $json->where('data.attributes.visibility', 'public');
                $json->where('data.attributes.storage.capacity', 200);
                $json->where('data.attributes.storage.items_count', 0);
                $json->where('data.attributes.collaborators_count', 0);
                $json->where('data.attributes.storage.is_full', false);
                $json->where('data.attributes.storage.available', 200);
                $json->where('data.attributes.storage.percentage_used', 0);
                $json->where('data.attributes.has_description', true);
                $json->where('data.attributes.name', $folder->name->present());
                $json->where('data.attributes.description', $folder->description);
                $json->where('data.attributes.date_created', (string) $folder->created_at);
                $json->where('data.attributes.last_updated', (string) $folder->updated_at);
            })
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'attributes' => [
                        'id',
                        'name',
                        'description',
                        'collaborators_count',
                        'has_description',
                        'date_created',
                        'last_updated',
                        'visibility',
                        'storage' => [
                            'items_count',
                            'capacity',
                            'is_full',
                            'available',
                            'percentage_used'
                        ]
                    ]
                ]
            ]);
    }

    public function testWillReturnCorrectBookmarksCount(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $repository = new CreateFolderBookmarks();

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->create();
        $bookmarks = BookmarkFactory::times(2)->for($user)->create();

        $repository->create($folder->id, $bookmarks->pluck('id')->all());

        $this->fetchFolderResponse(['folder_id' => $publicId = $folder->public_id->present()])
            ->assertOk()
            ->assertJsonPath('data.attributes.storage.items_count', 2);

        $bookmarks->first()->delete();

        $this->fetchFolderResponse(['folder_id' => $publicId])
            ->assertOk()
            ->assertJsonPath('data.attributes.storage.items_count', 1);
    }

    public function testWillReturnCorrectCollaboratorsCount(): void
    {
        $users = UserFactory::times(2)->create();

        $this->loginUser($folderOwner = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($users[0], $folder, UAC::all()->toArray());
        $this->CreateCollaborationRecord($users[1], $folder, UAC::all()->toArray());

        $this->fetchFolderResponse(['folder_id' => $publicId = $folder->public_id->present()])
            ->assertOk()
            ->assertJsonPath('data.attributes.collaborators_count', 2);

        $users->first()->delete();

        $this->fetchFolderResponse(['folder_id' => $publicId])
            ->assertOk()
            ->assertJsonPath('data.attributes.collaborators_count', 1);
    }

    public function testWhenFolderIsPrivate(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->private()->create();

        $this->fetchFolderResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonPath('data.attributes.visibility', 'private');
    }

    #[Test]
    public function whenFolderIsCollaboratorsOnly(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->visibleToCollaboratorsOnly()->create();

        $this->fetchFolderResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonPath('data.attributes.visibility', 'collaborators');
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->fetchFolderResponse(['folder_id' => $this->generateFolderId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchFolderResponse(['folder_id' => FolderFactory::new()->create()->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testRequestPartialResource(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->fetchFolderResponse([
            'folder_id' => $folder->public_id->present(),
            'fields' => 'id,name,description'
        ])
            ->assertOk()
            ->assertJsonCount(3, 'data.attributes')
            ->assertJson(function (AssertableJson $json) use ($folder) {
                $json->etc();
                $json->where('data.attributes.id', $folder->public_id->present());
                $json->where('data.attributes.name', $folder->name->present());
                $json->where('data.attributes.description', $folder->description);
            })
            ->assertJsonStructure([
                "data" => [
                    "type",
                    "attributes" => [
                        "id",
                        "name",
                        "description",
                    ]
                ]
            ]);
    }

    public function testWillReturnUnprocessableWhenFieldsParameterIsInValid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchFolderResponse([
            'folder_id' => $publicId = $this->generateFolderId()->present(),
            'fields' => 'id,name,foo,1'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.2 is invalid.']
            ]);

        $this->fetchFolderResponse([
            'folder_id' => $publicId,
            'fields' => '1,2,3,4'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.0 is invalid.']
            ]);

        $this->fetchFolderResponse([
            'folder_id' => $publicId,
            'fields' => 'id,name,description,description'
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => [
                    'The fields.2 field has a duplicate value.',
                ]
            ]);

        $this->fetchFolderResponse([
            'folder_id' => $publicId,
            'fields' => 'id,name,storage,storage.items_count'
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['Cannot request the storage field with any of its child attributes.']
            ]);
    }
}
