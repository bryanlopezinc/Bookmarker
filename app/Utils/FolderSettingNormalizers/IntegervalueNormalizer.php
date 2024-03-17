<?php

declare(strict_types=1);

namespace App\Utils\FolderSettingNormalizers;

use App\Contracts\SettingAttributeNormalizerInterface;

final class IntegerValueNormalizer implements SettingAttributeNormalizerInterface
{
    public function normalize(mixed $value): mixed
    {
        return (int) $value;
    }
}
