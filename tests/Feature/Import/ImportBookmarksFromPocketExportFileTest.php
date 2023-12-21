<?php

namespace Tests\Feature\Import;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;

class ImportBookmarksFromPocketExportFileTest extends ImportBookmarkBaseTest
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

        $this->importBookmarkResponse([
            'source'             => 'pocketExportFile',
            'pocket_export_file' => UploadedFile::fake()->create('file.html', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pocket_export_file' => ['The pocket export file must not be greater than 5000 kilobytes.']
            ]);
    }

    public function testWillImportBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        Passport::actingAs($user = UserFactory::new()->create());

        $content = file_get_contents(base_path('tests/stubs/Imports/pocketExportFile.html'));

        $this->withRequestId();
        $this->importBookmarkResponse([
            'request_id' => $this->faker->uuid,
            'source' => 'pocketExportFile',
            'pocket_export_file' => UploadedFile::fake()->createWithContent('file.html', $content),
        ])->assertStatus(Response::HTTP_PROCESSING);

        $this->assertEquals(Bookmark::query()->where('user_id', $user->id)->count(), 11);
    }
}
