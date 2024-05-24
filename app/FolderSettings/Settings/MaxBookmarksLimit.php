<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings;

use App\ValueObjects\FolderStorage;

final class MaxBookmarksLimit extends AbstractSetting
{
    /**
     * @param int $limit
     */
    public function __construct($limit = -1)
    {
        parent::__construct($limit);
    }

    public function value(): int
    {
        return (int) $this->value;
    }

    /**
     * @inheritdoc
     */
    public function id(): string
    {
        return 'max_bookmarks_limit';
    }

    /**
     * @inheritdoc
     */
    protected function rules(): array
    {
        return [
            'sometimes',
            'int',
            'min:-1',
            'max:' . FolderStorage::MAX_ITEMS
        ];
    }
}
