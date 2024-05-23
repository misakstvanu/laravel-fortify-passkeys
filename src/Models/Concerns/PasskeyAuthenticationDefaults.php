<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Models\Concerns;


use Illuminate\Database\Eloquent\Relations\HasMany;
use Misakstvanu\LaravelFortifyPasskeys\Models\Passkey;

trait PasskeyAuthenticationDefaults{

    public function passkeys() :HasMany {
        return $this->hasMany(Passkey::class);
    }

}
