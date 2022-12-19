<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator as Factory;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class FolderSettingsRule implements RuleContract, DataAwareRule
{
    private const ALLOWED = [
        'N-enable',
        'N-newCollaborator',
        'N-onlyNewCollaboratorsByMe',
        'N-updated',
        'N-newBookmarks',
        'N-bookmarkDelete',
        'N-collaboratorExit',
        'N-collaboratorExitOnlyHasWritePermission'
    ];

    private MessageBag $errors;
    private array $data;
    private string $attribute;

    public function __construct()
    {
        $this->errors = new MessageBag();
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->attribute = $attribute;

        $validator = Factory::make([$attribute => $value], $this->validationRules())
            ->after(function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (
                    $this->input('N-newCollaborator', false) === false &&
                    $this->input('N-onlyNewCollaboratorsByMe', false) === true
                ) {
                    $validator->errors()->add(
                        $this->attribute,
                        'The settings N-onlyNewCollaboratorsByMe cannot be true when N-newCollaborator is false.'
                    );
                }

                if (
                    $this->input('N-collaboratorExit', false) === false &&
                    $this->input('N-collaboratorExitOnlyHasWritePermission', false) === true
                ) {
                    $validator->errors()->add(
                        $this->attribute,
                        'The settings N-collaboratorExitOnlyHasWritePermission cannot be true when N-collaboratorExit is false.'
                    );
                }
            });

        $this->errors->merge($validator->errors());

        return $this->errors->isEmpty();
    }

    private function validationRules(): array
    {
        $notificationsIsDisabled = $this->input("N-enable", true) === false;

        return [
            $this->attribute => ['bail', 'array', 'filled'],
            $this->join('*') => [Rule::in(self::ALLOWED)],
            $this->join('N-enable') => ['bool'],
            $this->join('N-newCollaborator') => $notificationRules = ['sometimes', 'bool', Rule::prohibitedIf($notificationsIsDisabled)],
            $this->join('N-onlyNewCollaboratorsByMe') => $notificationRules,
            $this->join('N-updated') => $notificationRules,
            $this->join('N-newBookmarks') => $notificationRules,
            $this->join('N-bookmarkDelete') => $notificationRules,
            $this->join('N-collaboratorExit') => $notificationRules,
            $this->join('N-collaboratorExitOnlyHasWritePermission') => $notificationRules,
        ];
    }

    private function join(string $value): string
    {
        return "$this->attribute.$value";
    }

    private function input(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $this->join($key), $default);
    }

    /**
     * @return array
     */
    public function message()
    {
        return $this->errors->all();
    }
}
