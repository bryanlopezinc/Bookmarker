<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CollaboratorMetricType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int                    $id
 * @property int                    $count
 * @property int                    $collaborator_id
 * @property int                    $folder_id
 * @property \Carbon\Carbon         $timestamp
 * @property CollaboratorMetricType $metrics_type
 */
final class FolderCollaboratorMetric extends Model
{
    public const UPDATED_AT = null;
    public const  CREATED_AT = 'timestamp';

    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_collaborators_metrics';

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
