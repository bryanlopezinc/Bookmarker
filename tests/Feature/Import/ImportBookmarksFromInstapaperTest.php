<?php

namespace Tests\Feature\Import;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;

class ImportBookmarksFromInstapaperTest extends ImportBookmarkBaseTest
{
    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->importBookmarkResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The source field is required.'],
            ]);

        $this->importBookmarkResponse(['source' => 'instapaperFile',])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'instapaper_html' => ['The instapaper html field is required.']
            ]);

        $this->importBookmarkResponse([
            'source' => 'instapaperFile',
            'instapaper_html' => UploadedFile::fake()->create('file.html', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'instapaper_html' => ['The instapaper html must not be greater than 5000 kilobytes.']
            ]);

        $this->importBookmarkResponse([
            'source' => 'instapaperFile',
            'tags'   => TagFactory::new()->count(16)->make()->pluck('name')->implode(',')
        ])->assertJsonValidationErrors([
            'tags' => 'The tags must not be greater than 15 characters.'
        ]);

        $this->importBookmarkResponse([
            'source' => 'instapaperFile',
            'tags' => 'howTo,howTo,stackOverflow'
        ])->assertJsonValidationErrors([
            "tags.0" => ["The tags.0 field has a duplicate value."],
            "tags.1" => ["The tags.1 field has a duplicate value."]
        ]);
    }

    public function testImportBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);
        Passport::actingAs($user = UserFactory::new()->create());

        $expectedUrls = [
            'https://symfony.com/',
            'https://redis.io/docs/getting-started/installation/install-redis-on-mac-os/',
            'https://www.goal.com/en',
            'https://laravel.com/'
        ];

        $this->importBookmarkResponse([
            'source' => 'instapaperFile',
            'instapaper_html' => UploadedFile::fake()->createWithContent('file.html', file_get_contents(base_path('tests/stubs/Imports/instapaper.html'))),
        ])->assertStatus(Response::HTTP_PROCESSING);

        Bookmark::query()
            ->where('user_id', $user->id)
            ->get()
            ->each(function (Bookmark $bookmark) use ($expectedUrls) {
                $this->assertContains($bookmark->url, $expectedUrls);
            });
    }
}
