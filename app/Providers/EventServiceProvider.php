<?php

namespace App\Providers;

use App\Events\LoginEvent;
use App\Listeners\Login\NotifyUserAboutNewLoginEventListener;
use App\Models\Bookmark;
use App\Observers\BookmarkObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Listeners\SendEmailVerificationNotification;

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
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Bookmark::observe([new BookmarkObserver]);
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
