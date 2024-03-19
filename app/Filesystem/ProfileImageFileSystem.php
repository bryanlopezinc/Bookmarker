<?php

declare(strict_types=1);

namespace App\Filesystem;

use Illuminate\Filesystem\FilesystemAdapter as Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ProfileImageFileSystem
{
    private const DISK = 'profileImages';
    private const DEFAULT = 'noImage.jpg';

    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: Storage::disk(self::DISK);
    }

    public function exists(string $fileName): bool
    {
        return $this->filesystem->exists($fileName);
    }

    public function store(UploadedFile $file): string|false
    {
        return $file->storePublicly('', self::DISK);
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
