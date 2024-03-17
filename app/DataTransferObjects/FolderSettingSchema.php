<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Contracts\SettingAttributeNormalizerInterface;

final class FolderSettingSchema
{
    public function __construct(
        public readonly string $Id,
        public readonly mixed $defaultValue,
        public readonly array $rules,
        public readonly array $rulesExternal,
        public readonly string $type,
        public readonly SettingAttributeNormalizerInterface $normalizer
    ) {
    }
}
