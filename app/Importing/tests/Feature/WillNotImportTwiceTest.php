<?php

namespace App\Importing\tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\View;
use Laravel\Passport\Passport;

class WillNotImportTwiceTest extends ImportBookmarkBaseTest
{
    public function testWillImportOnce(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $view = View::file(__DIR__ . '/../stubs/chromeExportFile.blade.php')
            ->with(['includeBookmarksBar' => false])
            ->with('bookmarks', [['url' => 'https://www.askapache.com/htaccess/']])
            ->render();

        $this->withRequestId();

        $this->importBookmarkResponse($parameters = [
            'source'     => 'chromeExportFile',
            'html'       => UploadedFile::fake()->createWithContent('file.html', $view),
        ])->assertStatus(Response::HTTP_PROCESSING);

        $this->importBookmarkResponse($parameters);
        $this->assertRequestAlreadyCompleted();
    }
}
