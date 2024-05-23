<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings;

final class MaxCollaboratorsLimit extends AbstractSetting
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
        return 'max_collaborators_limit';
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
            'max:' . setting('MAX_FOLDER_COLLABORATORS_LIMIT')
        ];
    }
}
