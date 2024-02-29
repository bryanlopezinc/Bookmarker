<?php

declare(strict_types=1);

namespace App\Importing\Models;

use App\Importing\DataTransferObjects\ImportHistoryTags;
use App\Importing\Enums\BookmarkImportStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $import_id
 * @property string $url
 * @property ImportHistoryTags $tags
 * @property BookmarkImportStatus $status
 * @property int $document_line_number
 */
final class ImportHistory extends Model
{
    /**
     * {@inheritdoc}
     */
    public $timestamps = false;

    /**
     * {@inheritdoc}
     */
    protected $table = 'imports_history';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'tags'   => 'json',
        'status' => BookmarkImportStatus::class,
    ];


    protected function tags(): Attribute
    {
        return new Attribute(
            get: function (string $tags) {
                return new ImportHistoryTags(json_decode($tags, true, JSON_THROW_ON_ERROR));
            },
        );
    }
}
