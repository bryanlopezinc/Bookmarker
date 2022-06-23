<?php

declare(strict_types=1);

/**
 * Getter function to retrieved a setting config.
 *
 * @throws Exception when the setting is not found.
 */
function setting(string $key): mixed
{
    $default = fn () => throw new Exception(
        sprintf('key [%s] is not defined in %s', $key, config_path('settings.php')),
        30_000
    );

    return config('settings.' . $key, $default);
}
