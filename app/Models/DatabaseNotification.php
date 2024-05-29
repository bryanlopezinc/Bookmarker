<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use App\Models\Relations\HasManyThroughJson;
use Illuminate\Notifications\DatabaseNotification as Model;
use App\Collections\ModelsCollection;

/**
 * @property string               $id
 * @property NotificationType     $type
 * @property array                $data
 * @property ModelsCollection     $resources
 * @property \Carbon\Carbon |null $created_at
 * @property \Carbon\Carbon       $created_at
 */
final class DatabaseNotification extends Model
{
    /**
     * {@inheritdoc}
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->casts = array_merge($this->casts, [
            'type' => NotificationType::class,
        ]);
    }

    public function resources(): HasManyThroughJson
    {
        return new HasManyThroughJson(
            $this,
            $this->getJsonDataForeignKeys(),
            $this->getColumnsForHasManyThroughJsonSelectStatement()
        );
    }

    private function getJsonDataForeignKeys(): array
    {
        $folderActivity = new FolderActivity();

        return [
            Folder::class => ['folder.id'],
            User::class   => $folderActivity->getJsonDataForeignKeys()[User::class] ?? []
        ];
    }

    private function getColumnsForHasManyThroughJsonSelectStatement(): array
    {
        $folderActivity = new FolderActivity();

        return [
            Folder::class => ['id', 'name'],
            User::class   => $folderActivity->getColumnsForHasManyThroughJsonSelectStatement()[User::class] ?? []
        ];
    }
}
