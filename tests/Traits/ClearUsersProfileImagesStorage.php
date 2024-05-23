<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Filesystem\ProfileImagesFilesystem;
use PHPUnit\Framework\Attributes\Before;

trait ClearUsersProfileImagesStorage
{
    #[Before]
    public function clearUsersProfileImagesStorage(): void
    {
        $this->beforeApplicationDestroyed(function () {
            $filesystem = new ProfileImagesFilesystem();

            $filesystem->clear();
        });
    }
}
