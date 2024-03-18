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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @property int $id
 * @property string|null $description
 * @property FolderName $name
 * @property string|null $password
 * @property FolderSettings $settings
 * @property FolderVisibility $visibility
 * @property int $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int $bookmarks_count
 * @property int $collaborators_count
 * @property \Illuminate\Database\Eloquent\Collection<FolderRole> $roles
 * @property \Illuminate\Database\Eloquent\Collection<User> $collaborators
 * @property \Illuminate\Database\Eloquent\Collection<Bookmark> $bookmarks
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

    public function collaborators(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            FolderCollaborator::class,
            'folder_id',
            'id',
            'id',
            'collaborator_id'
        );
    }

    public function roles(): HasMany
    {
        return $this->hasMany(FolderRole::class, 'folder_id', 'id');
    }

    public function bookmarks(): HasManyThrough
    {
        return $this->hasManyThrough(
            Bookmark::class,
            FolderBookmark::class,
            'folder_id', // Foreign key on the FolderBookmark table
            'id', // Foreign key on the Bookmark table
            'id', // Local key on the Folder table
            'bookmark_id' // Local key on the FolderBookmark table
        )->whereExists(Bookmark::whereRaw('id = folders_bookmarks.bookmark_id'));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyAttributes($builder, array $attributes = [])
    {
        if (empty($attributes)) {
            $builder->addSelect('folders.*')->withCount(['bookmarks', 'collaborators']);
        }

        return $builder;
    }
}
