<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * PHP Attributes implementing this interface will be called after a dataTransferObject have been created.
 */
interface AfterDTOSetUpHookInterface
{
    public function executeAfterSetUp(object $dataTransferObject): void;
}
