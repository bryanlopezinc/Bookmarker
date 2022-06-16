<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\CreatesBookmark;

class FetchUserTagsTest extends TestCase
{
    use CreatesBookmark;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('userTags', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/users/tags', 'userTags');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->getTestResponse(['per_page' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 50.']
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

    public function testWillFetchUserTags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->saveBookmark(['tags' => $tags = $this->faker->words()]);

        $response = $this->getTestResponse()
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data',
                'links' => [
                    'first',
                    'prev'
                ],
                'meta' => [
                    'current_page',
                    'path',
                    'per_page',
                    'has_more_pages'
                ]
            ]);

        $response->assertJsonStructure([
            'type',
            'attributes' => [
                'name'
            ]
        ], $response->json('data.0'));

        foreach ($response->json('data.*.attributes.name') as $tag) {
            $this->assertContains($tag, $tags);
        }
    }
}
