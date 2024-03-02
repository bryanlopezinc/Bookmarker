<?php

namespace App\Importing\tests\Feature;

use App\Enums\BookmarkCreationSource;
use App\Models\Bookmark;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\View;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;

class ImportBookmarkFromChromeTest extends ImportBookmarkBaseTest
{
    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->importBookmarkResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The source field is required.']
            ]);

        $this->importBookmarkResponse(['source' => 'chromeExportFile',])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'html' => ['The html field is required.']
            ]);

        $this->importBookmarkResponse(['source' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The selected source is invalid.']
            ]);

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'html' => UploadedFile::fake()->create('file.html', 1001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'html' => ['The html must not be greater than 1000 kilobytes.']
            ]);

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'tags'   => TagFactory::new()->count(16)->make()->pluck('name')->implode(',')
        ])->assertJsonValidationErrors([
            'tags' => 'The tags must not have more than 15 items.'
        ]);

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'tags' => 'howTo,howTo,stackOverflow'
        ])->assertJsonValidationErrors([
            "tags.0" => ["The tags.0 field has a duplicate value."],
            "tags.1" => ["The tags.1 field has a duplicate value."]
        ]);
    }

    #[Test]
    public function importBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->importBookmarkResponse([
            'source' => 'chromeExportFile',
            'html'   => UploadedFile::fake()->createWithContent('file.html', $this->getViewInstance()->render()),
        ])->assertStatus(Response::HTTP_PROCESSING);

        /** @var Bookmark */
        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEquals($userBookmark->created_from, BookmarkCreationSource::CHROME_IMPORT);
        $this->assertEquals('https://www.askapache.com/htaccess/', $userBookmark->url);
        $this->assertFalse($userBookmark->has_custom_title);
        $this->assertFalse($userBookmark->description_set_by_user);
        $this->assertEmpty($userBookmark->tags->all());
    }

    #[Test]
    public function willIncludeBookmarksInBookmarksBar(): void
    {
        $view = $this->getViewInstance()->with(['includeBookmarksBar' => true]);

        $this->loginUser($user = UserFactory::new()->create());
        $this->importBookmarkResponse([
            'source'     => 'chromeExportFile',
            'html'       => UploadedFile::fake()->createWithContent('file.html', $view->render()),
        ])->assertStatus(Response::HTTP_PROCESSING);

        $userBookmarks = Bookmark::query()->where('user_id', $user->id)->get(['id']);

        $this->assertCount(2, $userBookmarks);
    }

    private function getViewInstance()
    {
        return View::file(__DIR__.'/../stubs/chromeExportFile.blade.php')
            ->with(['includeBookmarksBar' => false])
            ->with('bookmarks', [
                ['url' => 'https://www.askapache.com/htaccess/'],
                ['url' => 'Invalid url that should not be imported']
            ]);
    }
}
