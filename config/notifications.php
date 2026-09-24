<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PCT Email Alerts
    |--------------------------------------------------------------------------
    |
    | When enabled, the PCT scan (rfa:scan-pct) also emails each assigned
    | officer a digest of their cases that are nearing, due today, or beyond
    | PCT. Emails go through the mailer set by MAIL_MAILER.
    |
    | Off by default: turn it on only after MAIL_MAILER points at a real
    | mail server. With the "log" mailer an alert is written to the log
    | but still recorded as sent, so it would not be emailed again later.
    |
    | max_items caps how many cases are listed in one email; the rest are
    | summarised with a link back to the system.
    |
    */

    'pct_email' => [

        'enabled' => (bool) env('PCT_EMAIL_ENABLED', false),

        'max_items' => (int) env('PCT_EMAIL_MAX_ITEMS', 50),

    ],

];
