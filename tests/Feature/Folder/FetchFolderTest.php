<?php

namespace Tests\Feature\Folder;

use App\Models\Folder as Model;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchFolderTest extends TestCase
{
    protected function fetchFolderResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchFolder', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders', 'fetchFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->fetchFolderResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchFolderResponse()->assertJsonValidationErrors(['id']);
    }

    public function testSuccessResponse(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->fetchFolderResponse(['id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(11, 'data.attributes')
            ->assertJson(function (AssertableJson $json) use ($folder) {
                $json->etc();
                $json->where('data.attributes.id', $folder->id);
                $json->where('data.attributes.is_public', false);
                $json->where('data.attributes.storage.capacity', 200);
                $json->where('data.attributes.storage.items_count', 0);
                $json->where('data.attributes.storage.is_full', false);
                $json->where('data.attributes.storage.available', 200);
                $json->where('data.attributes.storage.percentage_used', 0);
                $json->where('data.attributes.tags', []);
                $json->where('data.attributes.has_tags', false);
                $json->where('data.attributes.tags_count', 0);
                $json->where('data.attributes.has_description', true);
                $json->where('data.attributes.name', $folder->name);
                $json->where('data.attributes.description', $folder->description);
                $json->where('data.attributes.date_created', (string) $folder->created_at);
                $json->where('data.attributes.last_updated', (string) $folder->updated_at);
            })
            ->assertJsonStructure([
                "data" => [
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
                ]
            ]);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Model */
        $folder = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->fetchFolderResponse(['id' => $folder->id + 1])->assertNotFound();
    }

    public function testCanViewOnlyOwnFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchFolderResponse(['id' => FolderFactory::new()->create()->id])->assertForbidden();
    }
}
