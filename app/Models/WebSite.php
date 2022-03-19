<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WebSite extends Model
{
    public const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'sites';
    
    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}