<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 * @property int $user_id
 * @property \Carbon\Carbon $verified_at
 */
final class SecondaryEmail extends Model
{
    /**
     * {@inheritdoc}
     */
    public $timestamps = false;

    /**
     * {@inheritdoc}
     */
    protected $table = 'users_emails';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
