<?php

namespace Tests\Feature;

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
            ->assertSuccessful()
            ->assertJsonCount(10, 'data')
            ->assertJson(function (AssertableJson $json) use ($userfolders) {
                $json
                    ->etc()
                    ->where('links.first', route('userFolders', ['per_page' => 15, 'page' => 1]))
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) use ($userfolders) {
                        $attributes = $json->toArray();

                        $json->etc();
                        $this->assertContains($attributes['attributes']['id'], $userfolders->pluck('id')->all());

                        //Assert the name  and decription response sent to client is the sanitized version
                        $this->assertEquals($attributes['attributes']['name'], '&lt;script&gt;alert(Cross Site Scripting)&lt;/script&gt;');
                        $this->assertEquals($attributes['attributes']['description'], '&lt;script&gt;alert(CSS)&lt;/script&gt;');

                        (new AssertableJsonString($attributes))->assertStructure([
                            "type",
                            "attributes" => [
                                "id",
                                "name",
                                "description",
                                "date_created",
                                "last_updated",
                                "items_count",
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
            ->assertSuccessful()
            ->assertJson(function (AssertableJson $json) {
                $json->where('links.first', route('userFolders', ['per_page' => 20, 'page' => 1]))->etc();
            });
    }

    public function testPaginateResponse(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        FolderFactory::new()->count(20)->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['per_page' => 17])
            ->assertSuccessful()
            ->assertJsonCount(17, 'data');
    }
}
