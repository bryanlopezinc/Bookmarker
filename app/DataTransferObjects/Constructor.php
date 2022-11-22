<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

trait Constructor
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(protected array $attributes)
    {
        foreach ($this->attributes as $key => $value) {
            $this->{$key} = $value;
        }

        parent::__construct();
    }
}
