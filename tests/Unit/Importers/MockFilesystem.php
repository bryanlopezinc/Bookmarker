<?php

declare(strict_types=1);

namespace Tests\Unit\Importers;

use App\Importers\Filesystem;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;

trait MockFilesystem
{
    private function mockFilesystem(\Closure $mock): void
    {
        $filesystem = $this->getMockBuilder(FilesystemContract::class)->getMock();

        $mock($filesystem);

        $this->swap(Filesystem::class, new Filesystem($filesystem));
    }
}
