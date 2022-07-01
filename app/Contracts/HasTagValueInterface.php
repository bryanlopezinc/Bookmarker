<?php

declare(strict_types=1);

namespace App\Contracts;

interface HasTagValueInterface
{
    public function getTagValue(): string;
}
