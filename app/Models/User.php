<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\HasPublicIdInterface;
use App\Enums\TwoFaMode;
use App\ValueObjects\FullName;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int                            $id
 * @property UserPublicId                   $public_id
 * @property string                         $username
 * @property string                         $first_name
 * @property string                         $last_name
 * @property FullName                       $full_name
 * @property string                         $email
 * @property string                         $password
 * @property int                            $bookmarks_count
 * @property int                            $favorites_count
 * @property int                            $folders_count
 * @property int                            $secondary_emails_count
 * @property EloquentCollection<Folder>     $folders
 * @property EloquentCollection<Bookmark>   $bookmarks
 * @property \Carbon\Carbon|null            $email_verified_at
 * @property \Carbon\Carbon                 $created_at
 * @property \Carbon\Carbon                 $updated_at
 * @property TwoFaMode                      $two_fa_mode
 * @property string|null                    $profile_image_path
 * @property EloquentCollection<FolderRole> $roles
 */
class User extends Authenticatable implements MustVerifyEmail, HasPublicIdInterface
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_fa_mode'      => TwoFaMode::class,
        'public_id'        => UserPublicId::class,
    ];

    public static function fromRequest(Request $request): User
    {
        /** @var User */
        $user = $request->user();

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function getPublicIdentifier(): UserPublicId
    {
        return $this->public_id;
    }

    public function roles(): HasManyThrough
    {
        return $this->hasManyThrough(
            FolderRole::class,
            FolderCollaboratorRole::class,
            'collaborator_id',
            'id',
            'id',
            'role_id'
        );
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class, 'user_id', 'id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'user_id', 'id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'user_id', 'id');
    }

    public function secondaryEmails(): HasMany
    {
        return $this->hasMany(SecondaryEmail::class, 'user_id', 'id');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    public function findForPassport(string $emailOrUsername): ?self
    {
        /** @var self|null */
        $user = $this->query()
            ->where('username', $emailOrUsername)
            ->orWhere('email', $emailOrUsername)
            ->first();

        return $user;
    }

    public function getEmailName(): string
    {
        return 'email';
    }

    public function getFullNameOr(User $potentiallyOutedUserRecord): FullName
    {
        if ($this->exists) {
            return $this->full_name;
        }

        return $potentiallyOutedUserRecord->full_name;
    }

    public function getProfileImagePathOr(User $potentiallyOutedUserRecord): ?string
    {
        if ($this->exists) {
            return $this->profile_image_path;
        }

        return $potentiallyOutedUserRecord->profile_image_path;
    }

    protected function fullName(): Attribute
    {
        return new Attribute(
            get: fn (string $firstNameAndLastName) => new FullName($firstNameAndLastName),
            set: fn (string|FullName $fullName) => is_string($fullName) ? $fullName : $fullName->value
        );
    }

    public function activityLogContextVariables(): array
    {
        return [
            'id'                => $this->id,
            'full_name'         => $this->full_name->value,
            'public_id'         => $this->public_id->value,
            'profile_image_path' => $this->profile_image_path
        ];
    }
}
