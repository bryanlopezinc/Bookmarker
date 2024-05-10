<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                 $id
 * @property int                 $folder_id
 * @property int                 $collaborator_id
 * @property int                 $suspended_by
 * @property int|null            $duration_in_hours
 * @property User                $collaborator
 * @property User                $suspendedByUser
 * @property \Carbon\Carbon|null $suspended_until
 * @property \Carbon\Carbon      $suspended_at
 */
final class SuspendedCollaborator extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'suspended_collaborators';

    /**
     * {@inheritdoc}
     */
    public $timestamps = false;

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * @inheritdoc
     */
    protected $casts = [
        'suspended_until' => 'datetime',
        'suspended_at'    => 'datetime',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collaborator_id', 'id');
    }

    public function suspendedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by', 'id');
    }

    public function suspensionPeriodIsPast(): bool
    {
        $collaboratorIsSuspendedIndefinitely = $this->duration_in_hours === null;

        $suspensionEndPeriod = $this->suspended_at->addHours($this->duration_in_hours ?? 0);

        if ($collaboratorIsSuspendedIndefinitely) {
            return false;
        }

        return now()->isAfter($suspensionEndPeriod);
    }
}
