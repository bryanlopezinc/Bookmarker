<?php

declare(strict_types=1);

namespace App\Importing\Contracts;

use App\Importing\DataTransferObjects\ImportStats;

interface ReportableInterface
{
    public function getReport(): ImportStats;
}
