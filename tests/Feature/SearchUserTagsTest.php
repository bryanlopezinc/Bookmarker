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

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('searchUserTags'), $parameters);
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationExceptionWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('tag');
    }

    public function testSearchTags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->saveBookmark(['tags' => [$tag = $this->faker->word]]);

        $this->getTestResponse(['tag' => $tag])->assertJsonCount(1, 'data')->assertSuccessful();
    }
}
