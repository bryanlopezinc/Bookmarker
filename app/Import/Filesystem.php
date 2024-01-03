<?php

declare(strict_types=1);

namespace App\Import;

use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;

final class Filesystem
{
    public function __construct(private FilesystemContract $filesystem)
    {
    }

    public function put(string $contents, int $userID, string $importId): void
    {
        $this->filesystem->put($this->buildFileName($userID, $importId), $contents);
    }

    private function buildFileName(int $userID, string $importId): string
    {
        return implode('_', [$userID, $importId]);
    }

    public function delete(int $userID, string $importId): void
    {
        $this->filesystem->delete($this->buildFileName($userID, $importId));
    }

    public function exists(int $userID, string $importId): bool
    {
        return $this->filesystem->exists($this->buildFileName($userID, $importId));
    }

    public function get(int $userID, string $importId): string
    {
        $contents = $this->filesystem->get($this->buildFileName($userID, $importId));

        if ($contents === null) {
            return '';
        }

        return $contents;
    }
}
