<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAuthenticationOptionsRequest extends FormRequest {

    public function rules(): array {
        return [
            config('passkeys.username_column') => config('passkeys.username_column_validation'),
        ];
    }

}
