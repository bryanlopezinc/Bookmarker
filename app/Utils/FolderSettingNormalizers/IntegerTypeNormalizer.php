<?php

declare(strict_types=1);

namespace App\Utils\FolderSettingNormalizers;

use App\Contracts\SettingAttributeNormalizerInterface;

final class IntegerTypeNormalizer implements SettingAttributeNormalizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize(mixed $value): mixed
    {
        return (int) $value;
    }
}
