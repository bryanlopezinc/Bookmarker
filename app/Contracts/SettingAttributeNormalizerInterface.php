<?php

declare(strict_types=1);

namespace App\Contracts;

interface SettingAttributeNormalizerInterface
{
    public function normalize(mixed $value): mixed;
}
