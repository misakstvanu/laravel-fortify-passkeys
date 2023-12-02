<?php

namespace Misakstvanu\LaravelPasskeys\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface PasskeyAuthentication {

    public function passkeys() :HasMany;

}
