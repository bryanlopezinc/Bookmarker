<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

final class ProvidersCollection
{
    private const EXCEPT = [
        __CLASS__,
        TelescopeServiceProvider::class,
        BroadcastServiceProvider::class
    ];

    /**
     * @return Collection<class-string>
     */
    public static function getProviders(): Collection
    {
        $serviceProviders = [];

        foreach ((new Filesystem())->allFiles(__DIR__) as $file) {
            // Get the directory path if the provider is in a sub directory.
            $directoryPath = str_replace('/', '\\', $file->getRelativePath());

            if ( ! empty($directoryPath)) {
                $directoryPath .= '\\';
            }

            $serviceProvider = str(__NAMESPACE__)
                ->append('\\')
                ->append($directoryPath)
                ->append($file->getFilenameWithoutExtension())
                ->toString();

            if (in_array($serviceProvider, self::EXCEPT, true)) {
                continue;
            }

            $serviceProviders[] = $serviceProvider;
        }

        return new Collection($serviceProviders);
    }
}
