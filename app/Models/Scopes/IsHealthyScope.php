<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\BookmarkHealth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class IsHealthyScope implements Scope
{
    public function __construct(private readonly string $alias = 'isHealthy')
    {
    }

    public function __invoke(Builder $query): void
    {
        $query->addSelect([
            $this->alias => BookmarkHealth::query()
                ->select('status_code')
                ->whereColumn('bookmark_id', 'bookmarks.id')
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder, Model $model)
    {
        $this($builder);
    }
}
