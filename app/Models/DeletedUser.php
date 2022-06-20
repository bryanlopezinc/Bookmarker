<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 */
final class DeletedUser extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'deleted_users';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    public $timestamps = false;
}