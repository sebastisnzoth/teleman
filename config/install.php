<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installer step budget (seconds)
    |--------------------------------------------------------------------------
    |
    | Migrating and seeding the database is far too slow for one web request
    | on shared hosting, so the installer does the work in slices and the
    | browser keeps calling back until it is finished. Each slice stops
    | starting new work once this many seconds have elapsed. Keep it below
    | the host's PHP / proxy timeouts (30 s is common on shared hosting).
    |
    */

    'step_budget' => (float) env('INSTALL_STEP_BUDGET', 15),

];
