<?php

declare(strict_types=1);

namespace App\Importers;

use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;

final class Filesystem
{
    public function __construct(private FilesystemContract $filesystem)
    {
    }

    public function put(string $contents, UserID $userID, Uuid $requestID): void
    {
        $this->filesystem->put($this->buildFileName($userID, $requestID), $contents);
    }

    private function buildFileName(UserID $userID, Uuid $requestID): string
    {
        return implode('::', [$userID->value(), $requestID->value]);
    }

    public function delete(UserID $userID, Uuid $requestID): void
    {
        $this->filesystem->delete($this->buildFileName($userID, $requestID));
    }

    public function exists(UserID $userID, Uuid $requestID): bool
    {
        return $this->filesystem->exists($this->buildFileName($userID, $requestID));
    }

    public function get(UserID $userID, Uuid $requestID): string
    {
        $contents = $this->filesystem->get($this->buildFileName($userID, $requestID));

        if ($contents === null) {
            return '';
        }

        return $contents;
    }
}
