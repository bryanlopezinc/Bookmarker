<?php

declare(strict_types=1);

namespace App\Filesystem;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter as Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class FoldersIconsFilesystem
{
    private const DISK = 'foldersIcons';
    private const DEFAULT = 'noImage.jpg';

    private readonly Filesystem $filesystem;
    private readonly Application $app;

    public function __construct(Filesystem $filesystem = null, Application $app = null)
    {
        $this->filesystem = $filesystem ?: Storage::disk(self::DISK);
        $this->app = $app ?: app();
    }

    public function exists(string $fileName): bool
    {
        return $this->filesystem->exists($fileName);
    }

    public function clear(): void
    {
        if ($this->app->environment('production')) {
            throw new Exception('Cannot delete all files in production environment');
        }

        $this->filesystem->delete(
            $this->filesystem->allFiles()
        );
    }

    /**
     * @throws Throwable
     */
    public function store(UploadedFile $file): string
    {
        $path = $this->filesystem->putFileAs('', $file, $file->hashName());

        if ($path === false) {
            throw new Exception('Could not save folder icon');
        }

        return $path;
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