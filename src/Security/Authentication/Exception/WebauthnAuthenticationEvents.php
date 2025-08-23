<?php

declare(strict_types=1);

namespace Misakstvanu\LaravelFortifyPasskeys\Security\Authentication\Exception;

interface WebauthnAuthenticationEvents
{
    public const FAILURE = 'failure';

    public const SUCCESS = 'success';
}
