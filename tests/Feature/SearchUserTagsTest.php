<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\CreatesBookmark;

class SearchUserTagsTest extends TestCase
{
    use CreatesBookmark;

    protected function searchUserTagsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('searchUserTags', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/tags/search', 'searchUserTags');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->searchUserTagsResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationExceptionWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->searchUserTagsResponse()->assertJsonValidationErrorFor('tag');
    }

    public function testSearchTags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->saveBookmark(['tags' => [$tag = $this->faker->word]]);

        $this->searchUserTagsResponse(['tag' => $tag])
            ->assertJsonCount(1, 'data')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['name' => $tag]
                ]
            ]);
    }
}
