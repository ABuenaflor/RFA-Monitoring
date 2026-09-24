<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Administrator Account
    |--------------------------------------------------------------------------
    |
    | Read by UserSeeder to create/update the initial administrator login.
    | Set these in .env rather than editing this file.
    |
    */

    'email' => env('ADMIN_EMAIL', 'admin@rfa.local'),

    'password' => env('ADMIN_PASSWORD'),

];
