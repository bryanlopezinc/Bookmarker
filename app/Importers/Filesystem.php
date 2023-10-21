<?php

declare(strict_types=1);

namespace App\Importers;

use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;

final class Filesystem
{
    public function __construct(private FilesystemContract $filesystem)
    {
    }

    public function put(string $contents, int $userID, string $requestID): void
    {
        $this->filesystem->put($this->buildFileName($userID, $requestID), $contents);
    }

    private function buildFileName(int $userID, string $requestID): string
    {
        return implode('_', [$userID, $requestID]);
    }

    public function delete(int $userID, string $requestID): void
    {
        $this->filesystem->delete($this->buildFileName($userID, $requestID));
    }

    public function exists(int $userID, string $requestID): bool
    {
        return $this->filesystem->exists($this->buildFileName($userID, $requestID));
    }

    public function get(int $userID, string $requestID): string
    {
        $contents = $this->filesystem->get($this->buildFileName($userID, $requestID));

        if ($contents === null) {
            return '';
        }

        return $contents;
    }
}
