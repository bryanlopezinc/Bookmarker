<?php

declare(strict_types=1);

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
        return $this->getJson(route('userTags', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/tags', 'userTags');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->searchUserTagsResponse(['tag' => 'foo'])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->searchUserTagsResponse(['search' => str_repeat('f', 23)])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('search');
    }

    public function testSearchTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        (new TagRepository())->attach(
            [$tag = $this->faker->word],
            BookmarkFactory::new()->for($user)->create()
        );

        $this->searchUserTagsResponse(['search' => $tag])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', $tag)
            ->assertJsonCount(2, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.bookmarks_with_tag', 1)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'attributes' => [
                            'name',
                            'bookmarks_with_tag'
                        ]
                    ]
                ]
            ]);
    }
}
