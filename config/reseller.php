<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sub-accounts per Elite reseller
    |--------------------------------------------------------------------------
    */
    'max_sub_accounts_per_elite' => (int) env('RESELLER_MAX_SUB_ACCOUNTS', 50),

    /*
    |--------------------------------------------------------------------------
    | Defaults for accounts created by an Elite reseller
    |--------------------------------------------------------------------------
    */
    'sub_account_tier' => env('RESELLER_SUB_ACCOUNT_TIER', 'basic'),

    'sub_account_credits' => (int) env('RESELLER_SUB_ACCOUNT_CREDITS', 30),
];
