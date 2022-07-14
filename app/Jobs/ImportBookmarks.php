<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataTransferObjects\ImportData;
use App\Importers\Chrome\ImportBookmarksFromChromeBrowser as ChromeImporter;
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

            $this->when($importData->source()->isFromChrome(), function (ChromeImporter $importer) use ($importData) {
                $importer->import($importData->userID(), $importData->requestID(), $importData->data());
            });
        });
    }

    private function when(bool $condition, \Closure $closure): void
    {
        if (!$condition) {
            return;
        }

        $closureParameters = array_map(fn (string $class) => app($class), $this->closureParameterTypes($closure));

        $closure(...$closureParameters);
    }
}
