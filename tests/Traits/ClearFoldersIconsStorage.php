<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Filesystem\FoldersIconsFilesystem;
use PHPUnit\Framework\Attributes\Before;

trait ClearFoldersIconsStorage
{
    #[Before]
    public function clearFoldersIconsStorage(): void
    {
        $this->beforeApplicationDestroyed(function () {
            $filesystem = new FoldersIconsFilesystem();

            $filesystem->clear();
        });
    }
}
