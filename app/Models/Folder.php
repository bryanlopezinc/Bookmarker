<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\HasPublicIdInterface;
use App\Enums\FolderVisibility;
use App\ValueObjects\FolderName;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Database\Eloquent\Model;
use App\FolderSettings\FolderSettings;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property int                               $id
 * @property FolderPublicId                    $public_id
 * @property string|null                       $description
 * @property int                               $bookmarks_count
 * @property int                               $collaborators_count
 * @property string|null                       $password
 * @property int                               $user_id
 * @property FolderName                        $name
 * @property FolderSettings                    $settings
 * @property FolderVisibility                  $visibility
 * @property \Carbon\Carbon                    $created_at
 * @property \Carbon\Carbon                    $updated_at
 * @property Collection<FolderRole>            $roles
 * @property Collection<FolderActivity>        $activities
 * @property Collection<FolderCollaborator>    $collaborators
 * @property User                              $user
 * @property Collection<SuspendedCollaborator> $suspendedCollaborators
 * @property Collection<BlacklistedDomain>     $blacklistedDomains
 * @property Collection<User>                  $bannedUsers
 * @property Collection<MutedCollaborator>     $mutedCollaborators
 * @property Collection<Bookmark>              $bookmarks
 * @property Collection<FolderFeature>         $disabledFeatureTypes
 * @property string|null                       $icon_path
 */
final class Folder extends Model implements HasPublicIdInterface
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
        'password'   => 'hashed',
        'public_id'  => FolderPublicId::class
    ];

    /**
     * {@inheritdoc}
     */
    public function getPublicIdentifier(): FolderPublicId
    {
        return $this->public_id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    protected function settings(): Attribute
    {
        $set = function ($value) {
            if ($value instanceof FolderSettings) {
                return $value->toJson();
            }

            return (new FolderSettings($value))->toJson();
        };

        return new Attribute(
            get: fn (?string $json) => new FolderSettings(json_decode($json ?? '{}', true, JSON_THROW_ON_ERROR)),
            set: $set
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
        $whereExists = User::query()->whereColumn('id', 'collaborator_id');

        return $this->hasMany(FolderCollaborator::class, 'folder_id', 'id')->whereExists($whereExists);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(FolderActivity::class, 'folder_id', 'id');
    }

    public function bannedUsers(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            BannedCollaborator::class,
            'folder_id',
            'id',
            'id',
            'user_id'
        )->select(['users.id', 'full_name']);
    }

    public function disabledFeatureTypes(): HasManyThrough
    {
        return $this->hasManyThrough(
            FolderFeature::class,
            FolderDisabledFeature::class,
            'folder_id',
            'id',
            'id',
            'feature_id'
        );
    }

    public function suspendedCollaborators(): HasMany
    {
        return $this->hasMany(SuspendedCollaborator::class, 'folder_id', 'id');
    }

    public function blacklistedDomains(): HasMany
    {
        return $this->hasMany(BlacklistedDomain::class, 'folder_id', 'id');
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

    public function mutedCollaborators(): HasMany
    {
        return $this->hasMany(MutedCollaborator::class, 'folder_id', 'id');
    }

    public function activityLogContextVariables(): array
    {
        return [
            'id'        => $this->id,
            'public_id' => $this->public_id->value,
            'name'      => $this->name->value
        ];
    }

    public function getNameOr(Folder $potentiallyOutedFolderRecord): FolderName
    {
        if ($this->exists) {
            return $this->name;
        }

        return $potentiallyOutedFolderRecord->name;
    }

    public function wasCreatedBy(int|User $user): bool
    {
        if (is_int($user)) {
            $user = new User(['id' => $user]);

            $user->exists = true;
        }

        return $this->user_id === $user->id;
    }
}
