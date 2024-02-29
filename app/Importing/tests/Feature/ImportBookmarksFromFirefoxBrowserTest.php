<?php

namespace App\Importing\tests\Feature;

use App\Enums\BookmarkCreationSource;
use App\Models\Bookmark;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\View;
use Laravel\Passport\Passport;

class ImportBookmarksFromFirefoxBrowserTest extends ImportBookmarkBaseTest
{
    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->withRequestId();

        $this->importBookmarkResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The source field is required.']
            ]);

        $this->importBookmarkResponse(['source' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'source' => ['The selected source is invalid.']
            ]);

        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->create('file.html', 1001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'html' => ['The html must not be greater than 1000 kilobytes.']
            ]);
    }

    public function testImportBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->withRequestId();
        $this->importBookmarkResponse([
            'source' => 'firefoxFile',
            'html' => UploadedFile::fake()->createWithContent('file.html', $this->getViewInstance()->render()),
        ])->assertStatus(Response::HTTP_PROCESSING);

        /** @var Bookmark */
        $userBookmark = Bookmark::query()->with('tags')->where('user_id', $user->id)->sole();

        $this->assertEquals($userBookmark->created_from, BookmarkCreationSource::FIREFOX_IMPORT);
        $this->assertEquals('https://www.rottentomatoes.com/m/vhs99', $userBookmark->url);
        $this->assertFalse($userBookmark->has_custom_title);
        $this->assertFalse($userBookmark->description_set_by_user);
        $this->assertEmpty($userBookmark->tags->all());
    }

    private function getViewInstance()
    {
        return View::file(__DIR__ . '/../stubs/firefox.blade.php')
            ->with(['includeBookmarksInPersonalToolBar' => false])
            ->with('bookmarks', [
                ['url' => 'https://www.rottentomatoes.com/m/vhs99', 'tags' => ''],
                ['url' => 'fake', 'tags' => '']
            ]);
    }
}
