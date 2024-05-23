<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface PasskeyAuthentication {

    public function passkeys() :HasMany;

}
