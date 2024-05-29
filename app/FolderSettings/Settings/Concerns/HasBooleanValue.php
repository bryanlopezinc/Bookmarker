<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings\Concerns;

trait HasBooleanValue
{
    protected function normalize(mixed $value): mixed
    {
        return (bool) $value;
    }

    protected function rules(): array
    {
        return ['sometimes', 'bool'];
    }
}
