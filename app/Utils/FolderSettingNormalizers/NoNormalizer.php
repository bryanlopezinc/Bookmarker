<?php

declare(strict_types=1);

namespace App\Utils\FolderSettingNormalizers;

use App\Contracts\SettingAttributeNormalizerInterface;

final class NoNormalizer implements SettingAttributeNormalizerInterface
{
    public function normalize(mixed $value): mixed
    {
        return $value;
    }
}
