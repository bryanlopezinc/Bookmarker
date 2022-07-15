<?php

declare(strict_types=1);

namespace App\Attributes;

use App\Collections\TagsCollection;
use Attribute;
use App\Contracts\AfterDTOSetUpHookInterface;
use App\DataTransferObjects\DataTransferObject;

#[Attribute(Attribute::TARGET_CLASS)]
final class EnsureValidTagsCount implements AfterDTOSetUpHookInterface
{
    /**
     * @param string $config The config key in config/settings.php
     * @param string $attribute The property or attribute name in the dTO
     */
    public function __construct(private string $config, private string $attribute)
    {
    }

    /**
     * @param DataTransferObject $resource
     */
    public function executeAfterSetUp(Object $resource): void
    {
        $maxTagsLength = setting($this->config);

        if (!$resource->offsetExists($this->attribute)) {
            return;
        }

        /** @var TagsCollection  */
        $tags = $resource->{$this->attribute};

        if ($tags->count() > $maxTagsLength) {
            throw new \Exception('Resource cannot have more than ' . $maxTagsLength . ' tags', 600);
        }
    }
}
