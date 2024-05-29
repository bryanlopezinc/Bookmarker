<?php

declare(strict_types=1);

namespace App\Models;

use App\ValueObjects\PublicId\BlacklistedDomainId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                 $id
 * @property BlacklistedDomainId $public_id
 * @property int                 $folder_id
 * @property string              $given_url
 * @property string              $resolved_domain
 * @property string              $domain_hash
 * @property int                 $created_by
 * @property User                $collaborator
 * @property \Carbon\Carbon|null $created_at
 */
final class BlacklistedDomain extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_blacklisted_domains';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'public_id' => BlacklistedDomainId::class
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
