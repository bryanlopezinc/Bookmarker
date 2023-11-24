<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FolderVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\ValueObjects\FolderSettings;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @property int $id
 * @property string|null $description
 * @property string $name
 * @property FolderSettings $settings
 * @property FolderVisibility $visibility
 * @property int $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int $bookmarksCount
 * @property int $collaboratorsCount
 * @method static Builder|QueryBuilder onlyAttributes(array $attributes = [])
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

    /**
     * {@inheritdoc}
     */
    protected $casts = ['visibility' => FolderVisibility::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    protected function settings(): Attribute
    {
        return new Attribute(
            get: fn (?string $json) => FolderSettings::make($json),
            set: fn ($value) => FolderSettings::make($value)->toJson()
        );
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyAttributes($builder, array $attributes = [])
    {
        $attributes = collect($attributes)->mapWithKeys(fn (string $col) => [$col => $col]);

        if ($attributes->isEmpty()) {
            $builder->addSelect('folders.*');
        }

        if (!$attributes->isEmpty()) {
            $builder->addSelect(
                $this->qualifyColumns($attributes->except(['bookmarks_count', 'collaboratorsCount'])->all())
            );
        }

        $builder->when($attributes->has('collaboratorsCount') || $attributes->isEmpty(), function ($query) {
            $query->addSelect([
                'collaboratorsCount' => FolderCollaborator::query()
                    ->selectRaw('COUNT(*)')
                    ->whereRaw("folder_id = {$this->getQualifiedKeyName()}")
                    ->whereExists(function (&$query) {
                        $query = User::query()
                            ->whereRaw('id = folders_collaborators.collaborator_id')
                            ->getQuery();
                    })
            ]);
        });

        $builder->when($attributes->has('bookmarks_count') || $attributes->isEmpty(), function ($query) {
            $query->addSelect([
                'bookmarksCount' => FolderBookmark::query()
                    ->selectRaw('COUNT(*)')
                    ->whereRaw("folder_id = {$this->qualifyColumn('id')}")
                    ->whereExists(function (&$query) {
                        $query = Bookmark::query()
                            ->whereRaw('id = folders_bookmarks.bookmark_id')
                            ->getQuery();
                    })
            ]);
        });

        return $builder;
    }
}
