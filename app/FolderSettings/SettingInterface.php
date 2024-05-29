<?php

declare(strict_types=1);

namespace App\FolderSettings;

use Illuminate\Contracts\Support\Arrayable;

interface SettingInterface extends Arrayable
{
    public static function fromArray(array $settings): SettingInterface;
}
