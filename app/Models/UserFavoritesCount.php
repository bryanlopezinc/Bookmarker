<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

final class UserFavoritesCount extends UserResourceCount
{
    public const TYPE = 4;

    /**
     * {@inheritdoc}
     */
    protected static function booted()
    {
        static::creating(function (UserFavoritesCount $model) {
            $model->type = self::TYPE;
        });

        static::addGlobalScope('type', function (Builder $builder) {
            $builder->where('type', self::TYPE);
        });
    }
}
