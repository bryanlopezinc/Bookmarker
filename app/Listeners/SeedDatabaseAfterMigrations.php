<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\DatabaseRefreshed;

final class SeedDatabaseAfterMigrations
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function handle(DatabaseRefreshed $event): void
    {
        $this->kernel->call('db:seed');
    }
}
