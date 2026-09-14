<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('model:prune')->daily();

// Outstanding-payment reminders (BUG-033). Runs hourly; the command decides
// which invoices are actually due, so the cadence stays config-driven.
Schedule::command('jigila:send-payment-reminders')->hourly()->withoutOverlapping();
