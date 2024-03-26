<?php

declare(strict_types=1);

namespace App\Importing\tests\Feature;

use App\Models\Bookmark;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\View;

class WillNotImportTwiceTest extends ImportBookmarkBaseTest
{
    public function testWillImportOnce(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $view = View::file(__DIR__ . '/../stubs/chromeExportFile.blade.php')
            ->with(['includeBookmarksBar' => false])
            ->with('bookmarks', [['url' => 'https://www.askapache.com/htaccess/']])
            ->render();

        $this->importBookmarkResponse(
            $parameters = [
                'source'     => 'chromeExportFile',
                'html'       => UploadedFile::fake()->createWithContent('file.html', $view),
            ],
            $headers = ['idempotency_key' => $this->faker->uuid]
        )->assertStatus(Response::HTTP_PROCESSING);

        $this->importBookmarkResponse($parameters, $headers);
        $this->importBookmarkResponse($parameters, $headers);

        $this->assertCount(1, Bookmark::query()->where('user_id', $user->id)->get());
    }
}
