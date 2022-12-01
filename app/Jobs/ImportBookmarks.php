<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DataTransferObjects\ImportData;
use App\Importers\Factory;
use App\Repositories\UserRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

final class ImportBookmarks implements ShouldQueue
{
    use Dispatchable,
        InteractsWithQueue,
        Queueable;

    public function __construct(private ImportData $importData)
    {
    }

    public function handle(Factory $factory, UserRepository $repository): void
    {
        if ($repository->findByID($this->importData->userID()) === false) {
            return;
        }

        DB::transaction(function () use ($factory) {
            $importData = $this->importData;

            $factory->getImporter($importData->source())->import(
                $importData->userID(),
                $importData->requestID(),
                $importData->data()
            );
        });
    }
}
