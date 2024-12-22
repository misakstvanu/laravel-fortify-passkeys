<?php

return [

    'user_model' => env('PASSKEYS_USER_MODEL', App\Models\User::class),

    'route_prefix' => env('PASSKEYS_ROUTE_PREFIX', 'passkey'),

    'route_middleware' => explode(',', env('PASSKEYS_ROUTE_MIDDLEWARE', 'web')),

    /*
     * This is just a fancy name for "domains that should not require HTTPS".
     * This is useful for local development or complex networks.
     * THIS OPTION IS DANGEROUS, USE CAREFULLY!
     */
    'relying_party_ids' => explode(',', env('PASSKEYS_RELYING_PARTY_IDS', '')),

    'username_column' => env('PASSKEYS_USERNAME_COLUMN', 'email'),

    'username_column_validation' => explode(',', env('PASSKEYS_USERNAME_COLUMN_VALIDATION', 'required,string,email,max:255')),

    'registration_user_validation' => explode(',', env('PASSKEYS_REGISTRATION_USER_VALIDATION', '')),

];
