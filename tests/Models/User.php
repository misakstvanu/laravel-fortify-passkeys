<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Tests\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Misakstvanu\LaravelFortifyPasskeys\Models\Contracts\PasskeyAuthentication;
use Misakstvanu\LaravelFortifyPasskeys\Models\Passkey;

class User extends Authenticatable implements PasskeyAuthentication
{
    protected $guarded = [];

    protected $table = 'users';

    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class);
    }
}
