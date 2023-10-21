<?php

namespace Tests\Feature;

use App\Repositories\TagRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SearchUserTagsTest extends TestCase
{
    use WithFaker;

    protected function searchUserTagsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('searchUserTags', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/tags/search', 'searchUserTags');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->searchUserTagsResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->searchUserTagsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('tag');
    }

    public function testSearchTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        (new TagRepository)->attach(
            [$tag = $this->faker->word],
            BookmarkFactory::new()->for($user)->create()
        );

        $this->searchUserTagsResponse(['tag' => $tag])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $tag)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['name']
                ]
            ]);
    }
}
