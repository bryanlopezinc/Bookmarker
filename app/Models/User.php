<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TwoFaMode;
use App\ValueObjects\FullName;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * @property        int                            $id
 * @property        string                         $username
 * @property        string                         $first_name
 * @property        string                         $last_name
 * @property        FullName                       $full_name
 * @property        string                         $email
 * @property        string                         $password
 * @property        int                            $bookmarks_count
 * @property        int                            $favorites_count
 * @property        int                            $folders_count
 * @property        \Carbon\Carbon|null            $email_verified_at
 * @property        \Carbon\Carbon                 $created_at
 * @property        \Carbon\Carbon                 $updated_at
 * @property        TwoFaMode                      $two_fa_mode
 * @property        string|null                    $profile_image_path
 * @property        EloquentCollection<FolderRole> $roles
 * @method   static Builder                        WithQueryOptions(array $columns = [])
 */
class User extends Authenticatable implements MustVerifyEmail
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
        'two_fa_mode' => TwoFaMode::class
    ];

    public static function fromRequest(Request $request): User
    {
        /** @var User */
        $user = $request->user();

        return $user;
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

    protected function fullName(): Attribute
    {
        return new Attribute(
            get: fn (string $firstNameAndLastName) => new FullName($firstNameAndLastName),
            set: fn (string|FullName $fullName) => is_string($fullName) ? $fullName : $fullName->value
        );
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    public function scopeWithQueryOptions($builder, array $columns = [])
    {
        $columns = collect($columns)->mapWithKeys(fn (string $value) => [$value => $value]);

        $builder->addSelect($this->getQualifiedKeyName());

        if ($columns->isEmpty()) {
            $builder->addSelect('users.*');
        }

        if ( ! $columns->isEmpty()) {
            $builder->addSelect(
                $this->qualifyColumns($columns->except(['bookmarks_count', 'folders_count', 'favorites_count'])->all())
            );
        }

        $this->addBookmarksCountQuery($builder, $columns);
        $this->addFavoritesCountQuery($builder, $columns);
        $this->addFoldersCountQuery($builder, $columns);

        return $builder;
    }

    /**
     * @param Builder $builder
     */
    private function addBookmarksCountQuery(&$builder, Collection $options): void
    {
        $wantsBookmarksCount = $options->has('bookmarks_count') ?: $options->isEmpty();

        if ( ! $wantsBookmarksCount) {
            return;
        }

        $builder->addSelect([
            'bookmarks_count' => Bookmark::query()
                ->selectRaw("COUNT(*)")
                ->whereRaw("user_id = {$this->qualifyColumn('id')}")
        ]);
    }

    /**
     * @param Builder $builder
     */
    private function addFavoritesCountQuery(&$builder, Collection $options): void
    {
        $wantsFavoritesCount = $options->has('favorites_count') ?: $options->isEmpty();

        if ( ! $wantsFavoritesCount) {
            return;
        }

        $builder->addSelect([
            'favorites_count' => Favorite::query()
                ->selectRaw("COUNT(*)")
                ->whereRaw("user_id = {$this->qualifyColumn('id')}")
        ]);
    }

    /**
     * @param Builder $builder
     */
    private function addFoldersCountQuery(&$builder, Collection $options): void
    {
        $wantsFoldersCount = $options->has('folders_count') ?: $options->isEmpty();

        if ( ! $wantsFoldersCount) {
            return;
        }

        $builder->addSelect([
            'folders_count' => Folder::query()
                ->selectRaw("COUNT(*)")
                ->whereRaw("user_id = {$this->qualifyColumn('id')}")
        ]);
    }
}
