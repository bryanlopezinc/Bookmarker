<?php

declare(strict_types=1);

namespace App\Import\Contracts;

use App\Import\ImportStats;

interface ReportableInterface
{
    public function getReport(): ImportStats;
}
