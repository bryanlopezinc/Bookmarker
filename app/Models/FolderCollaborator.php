<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class FolderCollaborator extends Model
{
    public const UPDATED_AT = null;
    public const CREATED_AT = 'joined_at';

    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_collaborators';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
