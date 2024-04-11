<?php

declare(strict_types=1);

namespace App\Filesystem;

use Illuminate\Filesystem\FilesystemAdapter as Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class FolderThumbnailFileSystem
{
    private const DISK = 'folderThumbnails';
    private const DEFAULT = 'noImage.jpg';

    private readonly Filesystem $filesystem;

    public function __construct(Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: Storage::disk(self::DISK);
    }

    public function exists(string $fileName): bool
    {
        return $this->filesystem->exists($fileName);
    }

    /**
     * @throws Throwable
     */
    public function store(UploadedFile $file): string
    {
        return $file->storePublicly('', self::DISK); //@phpstan-ignore-line
    }

    public function delete(?string $fileName): bool
    {
        if ( ! $fileName) {
            return false;
        }

        return $this->filesystem->delete($fileName);
    }

    public function publicUrl(?string $fileName): string
    {
        return $this->filesystem->url($fileName ?: self::DEFAULT);
    }
}
