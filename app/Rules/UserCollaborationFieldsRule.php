<?php

declare(strict_types=1);

namespace App\Rules;

final class UserCollaborationFieldsRule extends FolderFieldsRule
{
    public function __construct()
    {
        $this->allowedFields = array_merge($this->allowedFields, ['permissions']);

        parent::__construct();
    }
}
