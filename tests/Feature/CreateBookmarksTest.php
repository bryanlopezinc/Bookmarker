<?php

namespace Tests\Feature;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use App\Models\Taggable;
use App\Models\UserBookmarksCount;
use Database\Factories\TagFactory;
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
        $this->getTestResponse(['tags' => 'foo,bar,foobarzawqwe234urklslss,', ...$valid])->assertJsonValidationErrorFor('tags.2');
        $this->getTestResponse(['title' => ' ', ...$valid])->assertJsonValidationErrorFor('title');
    }

    public function testCannotAddMoreThan15Tags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $tags = TagFactory::new()->count(16)->make()->pluck('name')->implode(',');

        $this->getTestResponse(['url' => $this->faker->url, 'tags' => $tags])->assertJsonValidationErrors([
            'tags' => 'The tags must not be greater than 15 characters.'
        ]);
    }

    public function testTagsMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'url' => $this->faker->url,
            'tags' => 'howTo,howTo,stackOverflow'
        ])->assertJsonValidationErrors([
            "tags.0" => [
                "The tags.0 field has a duplicate value."
            ],
            "tags.1" => [
                "The tags.1 field has a duplicate value."
            ]
        ]);
    }

    public function testBookmarkDescriptionCannotExceed_200(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['url' => $this->faker->url, 'description' => str_repeat('a', 201)])->assertJsonValidationErrors([
            'description' => 'The description must not be greater than 200 characters.'
        ]);
    }

    public function testBookmarkTitleCannotExceed_100(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['url' => $this->faker->url, 'title' => str_repeat('a', 101)])->assertJsonValidationErrors([
            'title' => 'The title must not be greater than 100 characters.'
        ]);
    }

    public function testUrlMustNotBeHttp(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['url' => 'chrome://flags'])->assertCreated();
        $this->getTestResponse(['url' => 'sgn://social-network.example.com/?ident=bob'])->assertCreated();
    }

    public function testWillCreateBookmark(): void
    {
        Bus::fake(UpdateBookmarkWithHttpResponse::class);
        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse(['url' => $url =  $this->faker->url])->assertCreated();

        /** @var Bookmark */
        $bookmark = Bookmark::query()->where('user_id', $user->id)->sole();

        $this->assertEquals($url, $bookmark->title);
        $this->assertEquals($url, $bookmark->url);
        $this->assertEquals($url, $bookmark->url_canonical);
        $this->assertEquals($url, $bookmark->resolved_url);
        $this->assertEquals($user->id, $bookmark->user_id);
        $this->assertNull($bookmark->description);
        $this->assertFalse($bookmark->has_custom_title);
        $this->assertFalse($bookmark->description_set_by_user);

        $this->assertDatabaseHas(UserBookmarksCount::class, [
            'user_id' => $user->id,
            'count'   => 1,
            'type' => UserBookmarksCount::TYPE
        ]);
    }

    public function testCreateBookmarkWithTitle(): void
    {
        Bus::fake(UpdateBookmarkWithHttpResponse::class);

        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse([
            'url'   => $this->faker->url,
            'title' => $title = '<h1>whatever</h1>',
        ])->assertCreated();

        /** @var Bookmark */
        $bookmark = Bookmark::query()->where('user_id', $user->id)->sole();

        $this->assertEquals($title, $bookmark->title);
        $this->assertNull($bookmark->description);
        $this->assertTrue($bookmark->has_custom_title);
        $this->assertFalse($bookmark->description_set_by_user);
    }

    public function testCreateBookmarkWithDescription(): void
    {
        Bus::fake(UpdateBookmarkWithHttpResponse::class);

        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse([
            'url'   => $this->faker->url,
            'description' => $description = '<h2>my dog stepped on a bee :-(</h2>'
        ])->assertCreated();

        /** @var Bookmark */
        $bookmark = Bookmark::query()->where('user_id', $user->id)->sole();

        $this->assertEquals($description, $bookmark->description);
        $this->assertFalse($bookmark->has_custom_title);
        $this->assertTrue($bookmark->description_set_by_user);
    }

    public function testCreateBookmarkWithTags(): void
    {
        Bus::fake(UpdateBookmarkWithHttpResponse::class);

        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse([
            'url'   => $this->faker->url,
            'tags'  => TagFactory::new()->count(3)->make()->pluck('name')->implode(',')
        ])->assertCreated();

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id'   => Bookmark::query()->where('user_id', $user->id)->sole('id')->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE
        ]);
    }

    public function testWillDispatchJob(): void
    {
        Bus::fake(UpdateBookmarkWithHttpResponse::class);

        Passport::actingAs(UserFactory::new()->create());

        $this->withoutExceptionHandling()->getTestResponse(['url' => $url = $this->faker->url])->assertCreated();

        Bus::assertDispatchedTimes(UpdateBookmarkWithHttpResponse::class, 1);
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

    /**
     * bug fix
     */
    public function testEventHandlerWillHandleEventWhenSiteNameIsPresent(): void
    {
        $appName = \Illuminate\Support\Str::random(8);

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name ="application-name" content="$appName">
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
