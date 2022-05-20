<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id primary key
 * @property string $name
 * @property \Carbon\Carbon $created_at
 */
final class Tag extends Model
{
    public const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
