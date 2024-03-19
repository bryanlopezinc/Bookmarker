<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property \Carbon\Carbon      $muted_at
 * @property \Carbon\Carbon|null $muted_until
 */
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

    /**
     * @inheritdoc
     */
    protected $casts = [
        'muted_at' => 'datetime',
        'muted_until' => 'datetime',
    ];
}
