<?php

declare(strict_types=1);

namespace App\Rules;

final class UserCollaborationFieldsRule extends FolderFieldsRule
{
    public function __construct()
    {
        $this->ALLOWED = array_merge($this->ALLOWED, [
            'permissions',
            'permissions.canInviteUsers',
            'permissions.canAddBookmarks',
            'permissions.canRemoveBookmarks',
            'permissions.canUpdateFolder',
        ]);

        parent::__construct();
    }

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->errors->addIf(
            $this->hasDuplicatePermissionTypes($value),
            $attribute,
            'Cannot request permission with a permission child field'
        );

        return parent::passes($attribute, $value);
    }

    private function hasDuplicatePermissionTypes(array $fields): bool
    {
        $hasStorageType = collect($fields)->filter(function (string $field) {
            return in_array($field, [
                'permissions.canInviteUsers',
                'permissions.canAddBookmarks',
                'permissions.canRemoveBookmarks',
                'permissions.canUpdateFolder',
            ], true);
        })->isNotEmpty();

        return in_array('permissions', $fields, true) && $hasStorageType;
    }
}
