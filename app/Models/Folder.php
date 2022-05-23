<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $description
 * @property string $name
 * @property int $user_id foreign key to \App\Models\User
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class Folder extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
