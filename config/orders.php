<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Free cancellation window
    |--------------------------------------------------------------------------
    | Minutes after order creation during which a customer may cancel with no
    | charge (BUG-064). After the window the order can still be cancelled while
    | it sits in pending/processing, but the customer is warned that fees
    | already incurred may be deducted from any refund.
    */
    'free_cancellation_minutes' => env('ORDER_FREE_CANCELLATION_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Payment reminders
    |--------------------------------------------------------------------------
    | Hour-based reminder cadence for unpaid invoices (BUG-033). A reminder goes
    | out once per interval until the invoice is paid or the cap is reached.
    */
    'payment_reminder_interval_hours' => env('PAYMENT_REMINDER_INTERVAL_HOURS', 24),
    'payment_reminder_max'            => env('PAYMENT_REMINDER_MAX', 5),
];
