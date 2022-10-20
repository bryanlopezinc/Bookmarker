<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\DataTransferObjects\ImportData;
use App\Enums\ImportSource;
use App\ImporterFactory;
use App\Jobs\ImportBookmarks;
use App\Repositories\UserRepository;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Mockery\MockInterface;
use Tests\TestCase;

class ImportBookmarksTest extends TestCase
{
    public function test_will_not_import_bookmarks_if_user_has_deleted_account(): void
    {
        $importData = new ImportData(Uuid::generate(), ImportSource::CHROME, new UserID(33), []);

        $this->mock(UserRepository::class, function (MockInterface $m) {
            $m->shouldReceive('findByID')
                ->once()
                ->andReturn(false);
        });

        $this->mock(ImporterFactory::class, function (MockInterface $m) {
            $m->shouldReceive('getImporter')->never();
        });

        (new ImportBookmarks($importData))->handle(
            app(ImporterFactory::class),
            app(UserRepository::class)
        );
    }
}
