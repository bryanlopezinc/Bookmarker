<?php

declare(strict_types=1);

namespace App\Rules;

use Exception;
use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

abstract class AbstractFieldsRule implements ValidationRule
{
    /**
     * An array of the allowed fields
     *
     * @var array<string>
     */
    protected array $allowedFields;

    /**
     * An array of fields with their children names
     *
     * @var array<string,string[]>
     */
    protected array $parentChildrenMap = [];

    /**
     * The validation error bag
     */
    protected MessageBag $errors;

    public function __construct()
    {
        $this->errors = new MessageBag();
    }

    /**
     * @param string|array<string> $attributes
     */
    public function addAllowedAttributes(string|array $attributes): void
    {
        $attributes = (array) $attributes;

        if ($duplicates = array_intersect($this->allowedFields, $attributes)) {
            throw new Exception(
                sprintf('The attributes [%s] are already allowed', implode(',', $duplicates))
            );
        }

        $this->allowedFields = array_merge($this->allowedFields, $attributes);
    }

    /**
     * @return string[]
     */
    public function getAllowedFields(): array
    {
        return $this->allowedFields;
    }

    /**
     * {@inheritdoc}
     */
    public function passes($attribute, $value)
    {
        $validator = Validator::make([$attribute => $value], [
            $attribute     =>  ['array'],
            "$attribute.*" => ['distinct:strict', Rule::in($this->allowedFields)]
        ]);

        $this->errors->merge($validator->errors());

        if ($this->errors->isEmpty()) {
            $this->ensureDidNotRequestParentWithChildren($attribute, $value);
        }

        return $this->errors->isEmpty();
    }

    /**
     * Ensure a field was not requested with it's child attributes.
     * An example of such invalid request would be ['foo', 'foo.child']
     */
    private function ensureDidNotRequestParentWithChildren(string $attribute, array $values): void
    {
        foreach ($this->parentChildrenMap as $parent => $children) {
            if (in_array($parent, $values) && array_intersect($children, $values)) {
                $this->errors->add($attribute, "Cannot request the {$parent} field with any of its child attributes.");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function message()
    {
        return $this->errors->all();
    }

    public function errorBag(): MessageBag
    {
        return $this->errors;
    }
}
