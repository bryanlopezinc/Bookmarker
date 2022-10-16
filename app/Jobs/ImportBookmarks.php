<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataTransferObjects\ImportData;
use App\Enums\ImportSource;
use App\Importers\Chrome\Importer as ChromeImporter;
use App\Importers\ImporterInterface;
use App\Importers\Pocket\Importer as PocketImporter;
use App\Importers\Safari\Importer as SafariImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\ReflectsClosures;

final class ImportBookmarks implements ShouldQueue
{
    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        ReflectsClosures;

    public function __construct(private ImportData $importData)
    {
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $importData = $this->importData;

            $this->getImporter()->import($importData->userID(), $importData->requestID(), $importData->data());
        });
    }

    public function getImporter(): ImporterInterface
    {
        return match ($this->importData->source()) {
            ImportSource::CHROME => app(ChromeImporter::class),
            ImportSource::POCKET => app(PocketImporter::class),
            ImportSource::SAFARI => app(SafariImporter::class),
        };
    }
}
