<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

final class HasDuplicatesScope implements Scope
{
    public function __construct(private readonly string $alias = 'hasDuplicates')
    {
    }

    public function __invoke(Builder $query): void
    {
        $sql = <<<SQL
                EXISTS(
                    SELECT b.id
                    from bookmarks b
                    WHERE bookmarks.url_canonical_hash = b.url_canonical_hash
                    AND bookmarks.id != b.id)
        SQL;

        $query->addSelect([
            $this->alias => DB::query()->selectRaw($sql)
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
