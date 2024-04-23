<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\ValueObjects\PublicId\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class WherePublicIdScope implements Scope
{
    /**
     * @param publicId|iterable<PublicId> $publicId
     */
    public function __construct(
        private readonly PublicId|iterable $publicId,
        private readonly string $column = 'public_id'
    ) {
    }

    public function __invoke(Builder $query): void
    {
        if ($this->publicId instanceof PublicId) {
            $query->where($this->column, $this->publicId->value);
        } else {
            $query->whereIn($this->column, collect($this->publicId));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder, Model $model)
    {
        $this($builder);
    }
}
