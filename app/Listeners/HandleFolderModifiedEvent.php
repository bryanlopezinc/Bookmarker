<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Cache\Folder\FolderRepository;
use App\Events\FolderModifiedEvent;

final class HandleFolderModifiedEvent
{
    public function __construct(private FolderRepository $cacheRepository)
    {
    }

    public function handle(FolderModifiedEvent $event): void
    {
        $this->cacheRepository->forget($event->folderID);
    }
}
