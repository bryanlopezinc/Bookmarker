<?php

namespace Tests\Feature\Import;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;

class WillNotImportTwiceTest extends ImportBookmarkBaseTest
{
    public function testWillImportOnce(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        Passport::actingAs(UserFactory::new()->create());

        $this->withRequestId();

        $this->importBookmarkResponse($parameters = [
            'source'     => 'chromeExportFile',
            'html'       => UploadedFile::fake()->createWithContent('file.html', file_get_contents(base_path('tests/stubs/Imports/chromeExportFile.html'))),
        ])->assertStatus(Response::HTTP_PROCESSING);

        $this->importBookmarkResponse($parameters);
        $this->assertRequestAlreadyCompleted();
    }
}
