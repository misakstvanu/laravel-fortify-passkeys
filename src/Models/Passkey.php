<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passkey extends Model {
    use HasFactory;

    protected $fillable = [
        'credential_id',
        'public_key',
    ];

    protected $casts = [
        'public_key'    => 'encrypted:json',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function credentialId(): Attribute {
        return new Attribute(
            get: fn ($value) => base64_decode($value),
            set: fn ($value) => base64_encode($value),
        );
    }
}
