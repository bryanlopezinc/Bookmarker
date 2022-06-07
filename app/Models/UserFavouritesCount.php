<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

final class UserFavouritesCount extends UserResourceCount
{
    public const TYPE = 4;

    /**
     * {@inheritdoc}
     */
    protected static function booted()
    {
        static::creating(function (UserFavouritesCount $model) {
            $model->type = self::TYPE;
        });

        static::addGlobalScope('type', function (Builder $builder) {
            $builder->where('type', self::TYPE);
        });
    }
}
