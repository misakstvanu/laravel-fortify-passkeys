<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface PasskeyAuthentication {

    public function passkeys() :HasMany;

}
