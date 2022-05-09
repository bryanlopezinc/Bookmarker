<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserTagsTest extends TestCase
{
    use WithFaker;

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

        $this->saveBookmark($tags = $this->faker->words());

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

    private function saveBookmark(array $tags): void
    {
        Bus::fake();

        $this->postJson(route('createBookmark'), [
            'url' => $this->faker->url,
            'tags'  => implode(',', $tags)
        ])->assertSuccessful();
    }
}
