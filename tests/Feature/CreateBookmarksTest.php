<?php

namespace Tests\Feature;

use App\Jobs\UpdateBookmarkInfo;
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
        $this->getTestResponse(['tags' => 'foo,bar,foo bar,', ...$valid])->assertJsonValidationErrorFor('tags.2');
        $this->getTestResponse(['tags' => '#foo', ...$valid])->assertJsonValidationErrorFor('tags.0');
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

    public function testWillCreateBookmark(): void
    {
        Bus::fake(UpdateBookmarkInfo::class);

        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse([
            'url' => $url =  $this->faker->url
        ])->assertCreated();

        $this->assertDatabaseHas(Bookmark::class, [
            'title' => $url,
            'user_id' => $user->id,
            'description' => null,
            'has_custom_title' => false,
            'description_set_by_user' => false,
            'url' => $url
        ]);

        $this->assertDatabaseHas(UserBookmarksCount::class, [
            'user_id' => $user->id,
            'count'   => 1,
            'type' => UserBookmarksCount::TYPE
        ]);
    }

    public function testCreateBookmarkWithTitle(): void
    {
        Bus::fake(UpdateBookmarkInfo::class);

        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse([
            'url'   => $this->faker->url,
            'title' => $title = '<h1>whatever</h1>',
        ])->assertCreated();

        $this->assertDatabaseHas(Bookmark::class, [
            'title' => $title,
            'user_id' => $user->id,
            'has_custom_title' => true,
            'description' => null,
            'description_set_by_user' => false,
        ]);
    }

    public function testCreateBookmarkWithDescription(): void
    {
        Bus::fake(UpdateBookmarkInfo::class);

        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse([
            'url'   => $this->faker->url,
            'description' => $description = '<h2>my dog stepped on a bee :-(</h2>'
        ])->assertCreated();

        $this->assertDatabaseHas(Bookmark::class, [
            'description' => $description,
            'user_id' => $user->id,
            'description_set_by_user' => true,
            'has_custom_title' => false,
        ]);
    }

    public function testCreateBookmarkWithTags(): void
    {
        Bus::fake(UpdateBookmarkInfo::class);

        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse([
            'url'   => $this->faker->url,
            'tags'  => TagFactory::new()->count(3)->make()->pluck('name')->implode(',')
        ])->assertCreated();

        $this->assertDatabaseHas(Taggable::class, [
            'tagged_by_id' => $user->id,
            'taggable_id'   => Bookmark::query()->where('user_id', $user->id)->sole('id')->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE
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

    public function testUnVerifiedUserCannotCreateBookmark(): void
    {
        Passport::actingAs(UserFactory::new()->unverified()->create());

        $this->getTestResponse(['url' => $this->faker->url])->assertForbidden();
    }
}
