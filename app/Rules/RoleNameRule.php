<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

final class RoleNameRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function validate($attribute, mixed $value, \Closure $fail): void
    {
        //if used in a attribute.* rule
        $parent = explode('.', $attribute)[0];

        $maxFolderRoleName = setting('MAX_FOLDER_ROLE_NAME');

        $validator = Validator::make(
            data: [$parent => $value],
            rules: [$parent => ['string', 'filled', "max:{$maxFolderRoleName}"]],
            attributes: [$parent => $attribute]
        );

        if ($validator->fails()) {
            $fail($validator->errors()->first());
        }
    }
}
