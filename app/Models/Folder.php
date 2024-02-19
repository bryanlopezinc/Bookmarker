<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FolderVisibility;
use App\ValueObjects\FolderName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\ValueObjects\FolderSettings;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @property int $id
 * @property string|null $description
 * @property FolderName $name
 * @property string $password
 * @property FolderSettings $settings
 * @property FolderVisibility $visibility
 * @property int $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int $bookmarksCount
 * @property int $collaborators_count
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
    protected $casts = [
        'visibility' => FolderVisibility::class,
        'password' => 'hashed',
    ];

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

    protected function name(): Attribute
    {
        return new Attribute(
            get: fn (string $name) => new FolderName($name),
            set: fn (mixed $name) => $name instanceof FolderName ? $name->value : (new FolderName($name))->value
        );
    }

    public function collaborators(): HasMany
    {
        $whereExists = User::whereRaw('id = folders_collaborators.collaborator_id');

        return $this->hasMany(FolderCollaborator::class, 'folder_id')->whereExists($whereExists);
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
                $this->qualifyColumns($attributes->except(['bookmarks_count'])->all())
            );
        }

        $builder->when($attributes->has('bookmarks_count') || $attributes->isEmpty(), function ($query) {
            $query->addSelect([
                'bookmarksCount' => FolderBookmark::query()
                    ->selectRaw('COUNT(*)')
                    ->whereRaw("folder_id = {$this->qualifyColumn('id')}")
                    ->whereExists(Bookmark::whereRaw('id = folders_bookmarks.bookmark_id'))
            ]);
        });

        return $builder;
    }
}
