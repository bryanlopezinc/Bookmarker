<?php

namespace App\Models;

use App\QueryColumns\UserQueryColumns;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * @property int $id
 * @property string $username
 * @property string $firstname
 * @property bool $lastname
 * @property bool $email
 * @property string $password
 * @property \Carbon\Carbon $email_verified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static Builder WithQueryOptions(UserQueryColumns $columns)
 */
final class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function findForPassport(string $username): ?self
    {
        return $this->where('username', $username)->first();
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    public function scopeWithQueryOptions($builder, UserQueryColumns $columns)
    {
        $builder->addSelect($this->getQualifiedKeyName());

        if ($columns->isEmpty()) {
            $builder->addSelect('users.*');
        }

        if (!$columns->isEmpty()) {
            $builder->addSelect($this->qualifyColumns($columns->except(['bookmarks_count'])));
        }

        $this->addBookmarksCountQuery($builder, $columns);
        $this->addFavouritesCountQuery($builder, $columns);

        return $builder;
    }

    /**
     * @param Builder $builder
     */
    private function addBookmarksCountQuery(&$builder, UserQueryColumns $options): void
    {
        $wantsBookmarksCount = $options->has('bookmarks_count') ?: $options->isEmpty();

        if (!$wantsBookmarksCount) {
            return;
        }

        $builder->join('users_resources_counts as bc', function (JoinClause $join) {
            $join->on('users.id', '=', 'bc.user_id')->where('bc.type', UserResourcesCount::BOOKMARKS_TYPE);
        }, type: 'left outer')->addSelect('bc.count as bookmarks_count');
    }

    /**
     * @param Builder $builder
     */
    private function addFavouritesCountQuery(&$builder, UserQueryColumns $options): void
    {
        $wantsFavouritesCount = $options->has('favourites_count') ?: $options->isEmpty();

        if (!$wantsFavouritesCount) {
            return;
        }

        $builder->join('users_resources_counts as fc', function (JoinClause $join) {
            $join->on('users.id', '=', 'fc.user_id')->where('fc.type', UserResourcesCount::FAVOURITES_TYPE);
        }, type: 'left outer')->addSelect('fc.count as favourites_count');
    }
}
