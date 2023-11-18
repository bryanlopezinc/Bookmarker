<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MutedCollaborator extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_muted_collaborators';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    public $timestamps = false;
}
