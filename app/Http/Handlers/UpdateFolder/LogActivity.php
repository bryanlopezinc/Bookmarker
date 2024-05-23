<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Http\Handlers\HasHandlersInterface;
use App\Models\Folder;
use Closure;

final class LogActivity implements HasHandlersInterface
{
    /**
     * @var array<callable(Folder):void>
     */
    private array $loggers;

    public function __construct(UpdateFolderRequestData $data)
    {
        $this->loggers = [
            new LogsActivity\FolderDescriptionChangedActivityLogger($data),
            new LogsActivity\FolderNameChangedActivityLogger($data),
            new LogsActivity\FolderIconChangedActivityLogger($data),
            new LogsActivity\FolderVisibilityChangedToPublicActivityLogger($data),
            new LogsActivity\FolderVisibilityChangedToCollaboratorsActivityLogger($data)
        ];
    }

    /**
     * @inheritdoc
     */
    public function getHandlers(): array
    {
        return $this->loggers;
    }

    public function __invoke(Folder $folder): void
    {
        foreach ($this->loggers as $logger) {
            $job = function () use ($logger, $folder) {
                $logger($folder);
            };

            dispatch($this->newLogActivityJobInstance($job))->afterResponse();
        }

        $this->loggers = [];
    }

    private function newLogActivityJobInstance(Closure $job): object
    {
        return new class ($job) {
            public function __construct(private readonly Closure $job)
            {
            }

            public function handle(): void
            {
                $this->job->__invoke();
            }
        };
    }
}
