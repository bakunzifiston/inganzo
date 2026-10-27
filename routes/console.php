<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs. Run `php artisan schedule:work` locally, or a cron entry calling
| `php artisan schedule:run` every minute in production (see docs/DEPLOYMENT.md).
*/

Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('orders:expire-unpaid')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('earnings:release')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('model:prune')->daily();
