<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Collections\BookmarksCollection;
use App\HealthChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CheckBookmarksHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private BookmarksCollection $bookmarks)
    {
    }

    public function handle(HealthChecker $healthChecker): void
    {
        $healthChecker->ping($this->bookmarks);
    }
}
