<?php

namespace App\Providers;

use App\Support\Migrations\BaselineMigrationGuard;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Console\Events\CommandStarting;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if (in_array($event->command, BaselineMigrationGuard::DESTRUCTIVE_COMMANDS, true)) {
                $connection = null;
                if ($event->input && $event->input->hasParameterOption('--database')) {
                    $val = $event->input->getParameterOption('--database', null);
                    if (is_string($val) && $val !== '') {
                        $connection = $val;
                    }
                }

                BaselineMigrationGuard::blockDestructiveCommandsWhenBaselineIsPresent($event->command, $connection);
            }
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
