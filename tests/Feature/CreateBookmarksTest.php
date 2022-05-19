<?php

namespace Tests\Feature;

use App\Jobs\UpdateBookmarkInfo;
use App\Models\UserResourcesCount;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreateBookmarksTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createBookmark'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks', 'createBookmark');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('url');
    }

    public function testWillThrowValidationWhenAttrbutesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $valid = [
            'url' => $this->faker->url
        ];

        $this->getTestResponse(['url' => 'foo bar'])->assertJsonValidationErrorFor('url');
        $this->getTestResponse(['tags' => ['foo', 'bar'], ...$valid])->assertJsonValidationErrorFor('tags');
        $this->getTestResponse(['tags' => 'foo,bar,foo bar,', ...$valid])->assertJsonValidationErrorFor('tags.2');
        $this->getTestResponse(['tags' => '#foo', ...$valid])->assertJsonValidationErrorFor('tags.0');
        $this->getTestResponse(['title' => ' ', ...$valid])->assertJsonValidationErrorFor('title');
    }

    public function testCannotAddMoreThan15Tags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $tags = implode(',', $this->faker->words(16));

        $this->getTestResponse(['url' => $this->faker->url, 'tags' => $tags])->assertJsonValidationErrors([
            'tags' => 'The tags must not be greater than 15 characters.'
        ]);
    }

    public function testWillAddWebPageToBookmarks(): void
    {
        Bus::fake();

        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse([
            'url' => $this->faker->url
        ])->assertCreated();

        $this->getTestResponse([
            'url'   => $this->faker->url,
            'title' => $this->faker->word
        ])->assertCreated();

        $this->getTestResponse([
            'url'   => $this->faker->url,
            'title' => $this->faker->word,
            'tags'  => implode(',', [$this->faker->word, $repeat = $this->faker->word, $repeat])
        ])->assertCreated();

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count'   => 3,
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ]);
    }

    public function testWillDispatchJob(): void
    {
        Bus::fake(UpdateBookmarkInfo::class);

        Passport::actingAs(UserFactory::new()->create());

        $this->withoutExceptionHandling()->getTestResponse(['url' => $url = $this->faker->url])->assertCreated();

        Bus::assertDispatchedTimes(UpdateBookmarkInfo::class, 1);
    }

    public function testEventHandlerWillHandleEvent(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Document</title>
            </head>
            <body>
            </body>
            </html>
        HTML;

        Http::fake(fn () => Http::response($html));

        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['url' => $this->faker->url])->assertCreated();
    }
}
