<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Model;
use App\Collections\ModelsCollection;
use App\Models\Relations\HasManyThroughJson;

/**
 * @property int              $id
 * @property int              $folder_id
 * @property ActivityType     $type
 * @property array            $data
 * @property ModelsCollection $resources
 * @property \Carbon\Carbon   $created_at
 */
final class FolderActivity extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_activities';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'data' => 'array',
        'type' => ActivityType::class
    ];

    public function resources(): HasManyThroughJson
    {
        return new HasManyThroughJson(
            $this,
            $this->getJsonDataForeignKeys(),
            $this->getColumnsForHasManyThroughJsonSelectStatement()
        );
    }

    public function getJsonDataForeignKeys(): array
    {
        return [
            User::class => [
                'inviter.id', 'invitee.id', 'collaborator.id', 'suspended_by.id',
                'suspended_collaborator.id', 'reinstated_by.id', 'collaborator_removed.id'
            ]
        ];
    }

    public function getColumnsForHasManyThroughJsonSelectStatement(): array
    {
        return [
            User::class => ['id', 'full_name', 'profile_image_path']
        ];
    }
}
