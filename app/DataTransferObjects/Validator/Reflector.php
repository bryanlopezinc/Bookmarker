<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Validator;

use App\Contracts\AfterDTOSetUpHookInterface;
use ReflectionClass;
use ReflectionAttribute;

class Reflector
{
    /**
     * Get class attrbutes and all of its parent attributes
     *
     * @return array<AfterDTOSetUpHookInterface>
     */
    public function getClassAttributesInstances(Object $object): array
    {
        $attrbutes = collect([]);
        $reflection = new ReflectionClass($object);

        while ($reflection !== false) {

            $attrbutes->push(...$reflection->getAttributes(AfterDTOSetUpHookInterface::class, ReflectionAttribute::IS_INSTANCEOF));

            $reflection = $reflection->getParentClass();
        }

        return $attrbutes
            ->reject(fn (?ReflectionAttribute $a): bool => is_null($a))
            ->map(fn (ReflectionAttribute $a): AfterDTOSetUpHookInterface => $a->newInstance())
            ->all();
    }
}
