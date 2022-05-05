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
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $username
 * @property string $firstname
 * @property bool $lastname
 * @property bool $email
 * @property string $password
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

        $sql = <<<SQL
                CASE
                    WHEN users_resources_counts.count IS NULL THEN 0
                    ELSE users_resources_counts.count
                END as 'bookmarks_count'
             SQL;

        $builder->addSelect(DB::raw($sql))->join('users_resources_counts', function (JoinClause $join) {
            $join->on('users.id', '=', 'users_resources_counts.user_id')
                ->where('users_resources_counts.type', UserResourcesCount::BOOKMARKS_TYPE);
        }, type: 'left outer');
    }
}
