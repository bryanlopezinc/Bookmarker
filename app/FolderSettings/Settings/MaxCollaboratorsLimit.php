<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings;

final class MaxCollaboratorsLimit extends AbstractSetting
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
