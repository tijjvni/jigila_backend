<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('model:prune')->daily();

// Payment deadlines (spec 4). Every fifteen minutes, not hourly: the "at
// expiry" reminder and the hold that follows it are both anchored to an exact
// deadline, and an hourly sweep would land them up to 59 minutes late on an
// invoice that is accruing a fee.
//
// Reminders run first so the expiry notice goes out before the hold that the
// second command places. Both are idempotent, so overlap between them is
// harmless — `withoutOverlapping()` is about not stacking long sweeps.
Schedule::command('jigila:send-payment-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('jigila:process-payment-deadlines')->everyFifteenMinutes()->withoutOverlapping();
