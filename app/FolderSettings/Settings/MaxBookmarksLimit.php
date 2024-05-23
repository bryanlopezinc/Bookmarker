<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings;

use App\ValueObjects\FolderStorage;

final class MaxBookmarksLimit extends AbstractSetting
{
    public function value(): int
    {
        if ( ! $this->isSet()) {
            return -1;
        }

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
