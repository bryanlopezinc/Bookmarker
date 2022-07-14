<?php

declare(strict_types=1);

namespace App\Importers;

use App\Importers\FilesystemInterface;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use Illuminate\Contracts\Filesystem\Filesystem as LaravelFilesystem;
use Illuminate\Support\Facades\Storage;

final class Filesystem implements FilesystemInterface
{
    private LaravelFilesystem $filesystem;

    public function __construct(string $disk)
    {
        $this->filesystem = Storage::disk($disk);
    }

    public function put(string $contents, UserID $userID, Uuid $requestID): void
    {
        $this->filesystem->put($this->buildFileName($userID, $requestID), $contents);
    }

    private function buildFileName(UserID $userID, Uuid $requestID): string
    {
        return implode('::', [$userID->toInt(), $requestID->value]);
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
        return $this->filesystem->get($this->buildFileName($userID, $requestID));
    }
}
