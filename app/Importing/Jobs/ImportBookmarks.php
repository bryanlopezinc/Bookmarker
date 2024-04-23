<?php

declare(strict_types=1);

namespace App\Importing\Jobs;

use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\Importer;
use App\Importing\Listeners;
use App\Importing\EventDispatcher;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class ImportBookmarks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(private ImportBookmarkRequestData $importData)
    {
    }

    public function handle(): void
    {
        $user = User::query()->whereKey($this->importData->userId())->first();

        if ( ! $user) {
            return;
        }

        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addListener(new Listeners\StoresImportHistory($this->importData));
        $eventDispatcher->addListener(new Listeners\UpdatesImportStatus());
        $eventDispatcher->addListener(new Listeners\TransferImportsToBookmarksStore($this->importData));
        $eventDispatcher->addListener(new Listeners\NotifiesUserOnImportFailure($user, $this->importData->importId()));
        $eventDispatcher->addListener(new Listeners\ClearsDataAfterImport($this->importData));

        $importer = new Importer(event: $eventDispatcher);

        $importer->import($this->importData);
    }
}
