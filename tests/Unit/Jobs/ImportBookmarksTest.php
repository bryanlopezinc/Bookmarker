<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\DataTransferObjects\ImportData;
use App\Enums\ImportSource;
use App\Importers\Factory;
use App\Jobs\ImportBookmarks;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;
use Tests\TestCase;

class ImportBookmarksTest extends TestCase
{
    use WithFaker;

    public function test_will_not_import_bookmarks_when_user_has_deleted_account(): void
    {
        $user = UserFactory::new()->create();

        $importData = new ImportData($this->faker->uuid, ImportSource::CHROME, $user->id, []);

        $user->delete();

        $this->mock(Factory::class, function (MockInterface $m) {
            $m->shouldReceive('getImporter')->never();
        });

        (new ImportBookmarks($importData))->handle(app(Factory::class));
    }
}
