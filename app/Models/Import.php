<?php

declare(strict_types=1);

namespace App\Models;

use App\Import\ImportStats;
use App\Import\ImportBookmarksStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $import_id
 * @property int $user_id
 * @property ImportBookmarksStatus $status
 * @property ImportStats $statistics
 * @property \Carbon\Carbon $created_at
 */
final class Import extends Model
{
    public const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'imports';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'status'     => ImportBookmarksStatus::class,
        'created_at' => 'datetime',
    ];

    protected function statistics(): Attribute
    {
        return new Attribute(
            get: function (?string $statistics) {
                return $statistics ? ImportStats::fromJson($statistics) : null;
            },
            set: function (?ImportStats $statistics) {
                return $statistics ? $statistics->toJson() : null;
            }
        );
    }
}
