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

class CreateBookmarkTest extends TestCase
{
    use WithFaker;

    protected function createBookmarkResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createBookmark'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks', 'createBookmark');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->createBookmarkResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createBookmarkResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('url');

        $this->createBookmarkResponse(['url' => 'foo bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('url');

        $this->createBookmarkResponse(['tags' => ['foo', 'bar']])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('tags');

        $this->createBookmarkResponse(['tags' => 'foo,bar,fooBarFooBarFoodFooBarA', 'url' => $this->faker->url])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('tags.2');

        $this->createBookmarkResponse([
            'url'  => $this->faker->url,
            'tags' => TagFactory::new()->count(16)->make()->pluck('name')->implode(',')
        ])->assertJsonValidationErrors([
            'tags' => 'The tags must not have more than 15 items.'
        ]);

        $this->createBookmarkResponse([
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

        $this->createBookmarkResponse(['url' => $this->faker->url, 'description' => str_repeat('a', 201)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'description' => 'The description must not be greater than 200 characters.'
            ]);

        $this->createBookmarkResponse(['url' => $this->faker->url, 'title' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title' => 'The title must not be greater than 100 characters.'
            ]);
    }

    public function testWillReturnUnprocessableWenUrlIsNotHttp(): void
    {
        Passport::actingAs(UserFactory::new()->make());

        $this->createBookmarkResponse(['url' => 'chrome://flags'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url' => 'The url must be a valid url']);

        $this->createBookmarkResponse(['url' => 'sgn://social-network.example.com/?ident=bob'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url' => 'The url must be a valid url']);
    }

    public function testWillCreateBookmark(): void
    {
        Bus::fake(UpdateBookmarkWithHttpResponse::class);
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createBookmarkResponse(['url' => $url =  $this->faker->url])->assertCreated();

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
    }

    public function testCreateBookmarkWithTitle(): void
    {
        Bus::fake(UpdateBookmarkWithHttpResponse::class);

        Passport::actingAs($user = UserFactory::new()->create());

        $this->createBookmarkResponse([
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

        $this->createBookmarkResponse([
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

        $this->createBookmarkResponse([
            'url'   => $this->faker->url,
            'tags'  => TagFactory::new()->count(3)->make()->pluck('name')->implode(',')
        ])->assertCreated();

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => Bookmark::query()->where('user_id', $user->id)->sole('id')->id,
        ]);
    }

    public function testWillDispatchJob(): void
    {
        Bus::fake(UpdateBookmarkWithHttpResponse::class);

        Passport::actingAs(UserFactory::new()->create());

        $this->withoutExceptionHandling()
            ->createBookmarkResponse(['url' => $this->faker->url])
            ->assertCreated();

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

        $this->createBookmarkResponse(['url' => $this->faker->url])->assertCreated();
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

        $this->createBookmarkResponse(['url' => $this->faker->url])->assertCreated();
    }
}
