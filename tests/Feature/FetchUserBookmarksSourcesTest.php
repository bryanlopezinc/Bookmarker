<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Factories\BookmarkFactory;
use Database\Factories\SourceFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\GeneratesId;

class FetchUserBookmarksSourcesTest extends TestCase
{
    use GeneratesId;

    protected function userBookmarksSourcesResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserBookmarksSources', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks/sources', 'fetchUserBookmarksSources');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->userBookmarksSourcesResponse()->assertUnauthorized();
    }

    public function testSuccess(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $source = SourceFactory::new()->create();

        BookmarkFactory::new()->for($user)->create(['source_id' => $source->id]);

        $this->userBookmarksSourcesResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', $source->name)
            ->assertJsonPath('data.0.attributes.id', $source->public_id->present());
    }
}
