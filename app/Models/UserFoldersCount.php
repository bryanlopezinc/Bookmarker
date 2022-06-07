<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

final class UserFoldersCount extends UserResourceCount
{
    public const TYPE = 5;

    /**
     * {@inheritdoc}
     */
    protected static function booted()
    {
        static::creating(function (UserFoldersCount $model) {
            $model->type = self::TYPE;
        });

        static::addGlobalScope('type', function (Builder $builder) {
            $builder->where('type', self::TYPE);
        });
    }
}
