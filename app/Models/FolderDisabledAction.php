<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class FolderDisabledAction extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_disabled_actions';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    public $timestamps = false;
}
