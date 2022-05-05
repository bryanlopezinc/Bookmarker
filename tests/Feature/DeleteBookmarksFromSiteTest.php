<?php

namespace Tests\Feature;

use App\Models\UserResourcesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\SiteFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeleteBookmarksFromSiteTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmarksFromSite'), $parameters);
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('site_id');
    }

    public function testWillDeleteBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $models = BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id,
            'site_id' => $siteId = SiteFactory::new()->create()->id
        ]);

        $shouldNotBeDeleted = BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id,
        ]);

        UserResourcesCount::create([
            'user_id' => $user->id,
            'count' => 10,
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ]);

        $this->getTestResponse(['site_id' => $siteId])->assertStatus(202);

        foreach ($shouldNotBeDeleted as $model) {
            $this->assertModelExists($model);
        }

        foreach ($models as $model) {
            $this->assertModelMissing($model);
        }

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count' => 5,
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ]);
    }
}
