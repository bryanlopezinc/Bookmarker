<?php

declare(strict_types=1);

namespace App\Models\Scopes\FetchCollaboratorsFilters;

use Illuminate\Database\Eloquent\Builder;

final class FilterByNameScope
{
    public function __construct(private readonly ?string $collaboratorName)
    {
    }

    public function __invoke(Builder $builder): void
    {
        if ($collaboratorName = $this->collaboratorName) {
            $builder->where('full_name', 'like', "{$collaboratorName}%");
        }
    }
}
