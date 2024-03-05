<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class FolderSettingSchema
{
    public function __construct(
        public readonly string $Id,
        public readonly mixed $defaultValue,
        public readonly array $rules,
        public readonly string $type
    ) {
    }

    public function isBooleanType(): bool
    {
        return $this->type === 'boolean';
    }

    public function isIntegerType(): bool
    {
        return $this->type === 'integer';
    }
}
