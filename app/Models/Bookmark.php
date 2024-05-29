<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookmarkCreationSource;
use App\ValueObjects\PublicId\BookmarkPublicId;
use App\Contracts\HasPublicIdInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * @property int                     $id
 * @property BookmarkPublicId        $public_id
 * @property string|null             $description
 * @property string                  $title
 * @property bool                    $has_custom_title
 * @property bool                    $description_set_by_user
 * @property string                  $url
 * @property string|null             $preview_image_url
 * @property int                     $user_id
 * @property int                     $source_id
 * @property string                  $url_canonical
 * @property string                  $url_canonical_hash
 * @property string                  $resolved_url
 * @property Source                  $source
 * @property EloquentCollection<Tag> $tags
 * @property bool                    $isHealthy
 * @property bool                    $isUserFavorite
 * @property bool                    $hasDuplicates
 * @property BookmarkCreationSource  $created_from
 * @property \Carbon\Carbon|null     $resolved_at
 * @property \Carbon\Carbon          $created_at
 * @property \Carbon\Carbon          $updated_at
 */
final class Bookmark extends Model implements HasPublicIdInterface
{
    public const DESCRIPTION_MAX_LENGTH = 200;
    public const TITLE_MAX_LENGTH       = 100;

    /**
     * {@inheritdoc}
     */
    protected $table = 'bookmarks';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'has_custom_title'        => 'bool',
        'description_set_by_user' => 'bool',
        'hasDuplicates'           => 'bool',
        'resolved_at'             => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
        'created_from'            => BookmarkCreationSource::class,
        'public_id'               => BookmarkPublicId::class
    ];

    /**
     * {@inheritdoc}
     */
    public function getPublicIdentifier(): BookmarkPublicId
    {
        return $this->public_id;
    }

    protected function isHealthy(): Attribute
    {
        $key = __FUNCTION__;

        if ( ! array_key_exists($key, $this->attributes)) {
            $this->throwMissingAttributeExceptionIfApplicable($key);
        }

        return new Attribute(
            get: function (?int $statusCode) {
                if ($statusCode === null) {
                    return true;
                }

                return $statusCode >= 200 && $statusCode < 300;
            },
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function tags(): HasManyThrough
    {
        return $this->hasManyThrough(Tag::class, Taggable::class, 'taggable_id', 'id', 'id', 'tag_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id', 'id');
    }

    public function activityLogContextVariables(): array
    {
        return [
            'id'        => $this->id,
            'public_id' => $this->public_id->value,
            'url'       => $this->url
        ];
    }
}
