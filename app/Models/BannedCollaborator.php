<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BannedCollaborator extends Model
{
    /**
     * {@inheritdoc}
     */
    public $timestamps = false;

    /**
     * {@inheritdoc}
     */
    protected $table = 'banned_collaborators';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
