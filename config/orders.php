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
    | Payment deadline
    |--------------------------------------------------------------------------
    | Hours from invoice issuance until payment is due. This is the fallback:
    | an admin may override it globally in settings (`payment_deadline_hours`)
    | or per order when raising the invoice.
    */
    'payment_deadline_hours' => env('PAYMENT_DEADLINE_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Deadline reminder stages
    |--------------------------------------------------------------------------
    | Hours *before* the deadline at which a reminder goes out; 0 means "at
    | expiry". Each stage fires at most once per deadline, tracked in
    | `invoices.reminder_stages_sent`, so the sweep can run as often as it likes.
    |
    | A stage whose trigger time already fell before the invoice was raised is
    | skipped rather than fired — a 24-hour deadline must not emit the "48 hours
    | left" notice seconds after the invoice email.
    */
    'payment_reminder_offsets_hours' => [48, 24, 6, 0],

    /*
    |--------------------------------------------------------------------------
    | Late fees
    |--------------------------------------------------------------------------
    | Accrues once the deadline (plus any grace) has lapsed. `mode` is one of
    | `none`, `flat` (flat USD per day) or `percent` (percent of the invoice per
    | day). Every key here is overridable live from admin settings.
    |
    | A day is counted as "a day or part thereof" — one hour late is one day.
    | `grace_hours` is the lever for softening that.
    |
    | Both caps apply: accrual stops after `max_days`, and the total fee never
    | exceeds `cap_percent` of the invoice.
    */
    'late_fee' => [
        'mode'        => env('LATE_FEE_MODE', 'percent'),
        'flat_amount' => env('LATE_FEE_FLAT_AMOUNT', 25),
        'percent'     => env('LATE_FEE_PERCENT', 1.5),
        'grace_hours' => env('LATE_FEE_GRACE_HOURS', 0),
        'max_days'    => env('LATE_FEE_MAX_DAYS', 30),
        'cap_percent' => env('LATE_FEE_CAP_PERCENT', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deadline extensions
    |--------------------------------------------------------------------------
    | A customer may request one extension per invoice through the portal; an
    | admin approves or declines it. `max_hours` caps what may be granted.
    |
    | Approving freezes the late fee already accrued rather than erasing it —
    | the extension forgives further accrual, not the days already late. An
    | admin who judges the delay to be Jigila's fault can pass
    | `waive_accrued_fees` on the approval to clear it as well.
    */
    'deadline_extension' => [
        'max_hours' => env('DEADLINE_EXTENSION_MAX_HOURS', 72),
    ],
];
