<?php

declare(strict_types=1);

namespace App\Contracts;

interface FolderSettingSchemaProviderInterface
{
    public function exists(string $settingId): bool;
}
