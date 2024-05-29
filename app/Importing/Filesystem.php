<?php

declare(strict_types=1);

namespace App\Importing;

use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;

final class Filesystem
{
    public function __construct(private FilesystemContract $filesystem)
    {
    }

    public function put(string $contents, string $fileName): void
    {
        $this->filesystem->put($fileName, $contents);
    }

    public function delete(string $fileName): void
    {
        $this->filesystem->delete($fileName);
    }

    public function exists(string $fileName): bool
    {
        return $this->filesystem->exists($fileName);
    }

    public function get(string $fileName): string
    {
        $contents = $this->filesystem->get($fileName);

        if ($contents === null) {
            return '';
        }

        return $contents;
    }
}
