<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\LoginEvent;
use App\Listeners\DeleteUnusedTags;
use App\Listeners\Login\NotifyUserAboutNewLoginEventListener;
use App\Listeners\SeedDatabaseAfterMigrations;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Listeners\SendEmailVerificationNotification;
use Illuminate\Database\Events\DatabaseRefreshed;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        LoginEvent::class => [NotifyUserAboutNewLoginEventListener::class],
        \App\Events\RegisteredEvent::class => [SendEmailVerificationNotification::class],
        \App\Events\ResendEmailVerificationLinkRequested::class => [SendEmailVerificationNotification::class],
        \App\Events\TagsDetachedEvent::class => [DeleteUnusedTags::class],
        DatabaseRefreshed::class => [SeedDatabaseAfterMigrations::class]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
