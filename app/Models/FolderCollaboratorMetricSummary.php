<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\CollaboratorMetricType;

/**
 * @property int                    $id
 * @property int                    $count
 * @property int                    $collaborator_id
 * @property \Carbon\Carbon         $created_at
 * @property CollaboratorMetricType $metrics_type
 * @property int                    $folder_id
 */
final class FolderCollaboratorMetricSummary extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_collaborators_metrics_summary';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    public $timestamps = true;

    /**
     * {@inheritdoc}
     */
    protected $casts = ['metrics_type' => CollaboratorMetricType::class];
}
