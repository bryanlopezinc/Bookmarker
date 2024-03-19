<?php

declare(strict_types=1);

namespace App\Importing\tests\Feature;

use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\View;
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
                'html' => ['The html field is required.']
            ]);

        $this->importBookmarkResponse([
            'source' => 'instapaperFile',
            'html' => UploadedFile::fake()->create('file.html', 1001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'html' => ['The html must not be greater than 1000 kilobytes.']
            ]);

        $this->importBookmarkResponse([
            'source' => 'instapaperFile',
            'tags'   => TagFactory::new()->count(16)->make()->pluck('name')->implode(',')
        ])->assertJsonValidationErrors([
            'tags' => 'The tags must not have more than 15 items.'
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
        Passport::actingAs(UserFactory::new()->create());

        $this->importBookmarkResponse([
            'source' => 'instapaperFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $this->getViewInstance()->render()),
        ])->assertStatus(Response::HTTP_PROCESSING);
    }

    private function getViewInstance()
    {
        return View::file(__DIR__ . '/../stubs/instapaper.blade.php')
            ->with('bookmarks', [['url' => 'https://symfony.com/']]);
    }
}
