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

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillFetchUserTags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->saveBookmark(['tags' => $tags = $this->faker->words()]);

        $response = $this->getTestResponse()
            ->assertSuccessful()
            ->assertJsonCount(count($tags), 'data')
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
