<?php

return [

    'user_model' => App\Models\User::class,

    'route_prefix' => 'passkey',

    'route_middleware' => ['web'],

    /*
     * This is just a fancy name for "domains that should not require HTTPS".
     * This is useful for local development or complex networks.
     * THIS OPTION IS DANGEROUS, USE CAREFULLY!
     */
    'relying_party_ids' => [],

    'username_column' => 'email',

    'registration_user_validation' => [],

];
