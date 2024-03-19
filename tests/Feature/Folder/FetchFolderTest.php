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
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;

class FetchFolderTest extends TestCase
{
    use CreatesCollaboration;

    protected function fetchFolderResponse(array $parameters = []): TestResponse
    {
        if (array_key_exists('id', $parameters)) {
            $parameters['id'] = (string) $parameters['id'];
        }

        return $this->getJson(route('fetchFolder', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders', 'fetchFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchFolderResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchFolderResponse()->assertJsonValidationErrors(['id']);
    }

    public function testFetchFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->create();

        $this->fetchFolderResponse(['id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(9, 'data.attributes')
            ->assertJson(function (AssertableJson $json) use ($folder) {
                $json->etc();
                $json->where('data.attributes.id', $folder->id);
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
        Passport::actingAs($user = UserFactory::new()->create());

        $repository = new CreateFolderBookmarks();

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->create();
        $bookmarks = BookmarkFactory::times(2)->for($user)->create();

        $repository->create($folder->id, $bookmarks->pluck('id')->all());

        $this->fetchFolderResponse(['id' => $folder->id])
            ->assertOk()
            ->assertJsonPath('data.attributes.storage.items_count', 2);

        $bookmarks->first()->delete();

        $this->fetchFolderResponse(['id' => $folder->id])
            ->assertOk()
            ->assertJsonPath('data.attributes.storage.items_count', 1);
    }

    public function testWillReturnCorrectCollaboratorsCount(): void
    {
        $users = UserFactory::times(2)->create();

        Passport::actingAs($folderOwner = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($users[0], $folder, UAC::all()->toArray());
        $this->CreateCollaborationRecord($users[1], $folder, UAC::all()->toArray());

        $this->fetchFolderResponse(['id' => $folder->id])
            ->assertOk()
            ->assertJsonPath('data.attributes.collaborators_count', 2);

        $users->first()->delete();

        $this->fetchFolderResponse(['id' => $folder->id])
            ->assertOk()
            ->assertJsonPath('data.attributes.collaborators_count', 1);
    }

    public function testWhenFolderIsPrivate(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->private()->create();

        $this->fetchFolderResponse(['id' => $folder->id])
            ->assertOk()
            ->assertJsonPath('data.attributes.visibility', 'private');
    }

    #[Test]
    public function whenFolderIsCollaboratorsOnly(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->visibleToCollaboratorsOnly()->create();

        $this->fetchFolderResponse(['id' => $folder->id])
            ->assertOk()
            ->assertJsonPath('data.attributes.visibility', 'collaborators');
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->create();

        $this->fetchFolderResponse(['id' => $folder->id + 1])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchFolderResponse(['id' => FolderFactory::new()->create()->id])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testRequestPartialResource(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->for($user)->create();

        $this->fetchFolderResponse([
            'id' => $folder->id,
            'fields' => 'id,name,description'
        ])
            ->assertOk()
            ->assertJsonCount(3, 'data.attributes')
            ->assertJson(function (AssertableJson $json) use ($folder) {
                $json->etc();
                $json->where('data.attributes.id', $folder->id);
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
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchFolderResponse([
            'id' => 33,
            'fields' => 'id,name,foo,1'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.2 is invalid.']
            ]);

        $this->fetchFolderResponse([
            'id' => 33,
            'fields' => '1,2,3,4'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.0 is invalid.']
            ]);

        $this->fetchFolderResponse([
            'id' => 33,
            'fields' => 'id,name,description,description'
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => [
                    'The fields.2 field has a duplicate value.',
                ]
            ]);

        $this->fetchFolderResponse([
            'id' => 33,
            'fields' => 'id,name,storage,storage.items_count'
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['Cannot request the storage field with any of its child attributes.']
            ]);
    }
}
