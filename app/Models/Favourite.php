<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Favourite extends Model
{
    const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'favourites';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
