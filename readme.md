> [!WARNING]  
> This project is currently under development

# Laravel Passkeys
This package provides a simple way to authenticate users using passkeys. 

Authentication processes are based on `web-auth/webauthn-lib` package. On frontend, the opposite functionality is provided by `@simplewebauthn/browser` package.

## Installation

1. Install the package via composer:

``` bash
composer require misakstvanu/laravel-fortify-passkeys
```

2. Service provider will be auto discovered. If you want to register it manually, add the following line to
   your `config/app.php`

``` php
'providers' => [
    /*
     * Package Service Providers...
     */
    // ...
    Misakstvanu\LaravelFortifyPasskeys\PasskeysServiceProvider::class,
];
```

3. Publish migration to create `passkeys` table:

``` bash
php artisan vendor:publish --tag=laravel-fortify-passkeys-migrations
php artisan migrate
```

4. (optional) Publish the config file:

``` bash
php artisan vendor:publish --tag=laravel-fortify-passkeys-config
```

## Configuration

1. Implement an interface `Misakstvanu\LaravelFortifyPasskeys\Contracts\PasskeyAuthentication` on your `User` model:
``` php
use Misakstvanu\LaravelFortifyPasskeys\Models\Contracts\PasskeyAuthentication;

class User extends Authenticatable implements PasskeyAuthentication {
    // ...
}
```

2. Set up `passkeys` relation on your `User` model:
``` php
use Misakstvanu\LaravelFortifyPasskeys\Models\Passkey;

public function passkeys() :HasMany {
    return $this->hasMany(Passkey::class);
}
```

3. Once you have published the config file, you can configure the package by editing the `config/passkeys.php` file. The variables are:

- `user_model` - the model that will be used to authenticate the user. Default: `App\Models\User`
- `route_prefix` - prefix for the 4 routes this package loads. Default: `passkeys`
- `route_middleware` - middleware that will be applied to the routes. Default: `['web']`
- `username_column` - the column that will be used to find the user. Default: `email`
- `relying_party_ids` - an array of domains that will be allowed insecure connection, use with caution. Default: `[]`
- `registration_user_validation` - validation rules that will be applied to the request when registering new user. These values will then be persisted with the new user. Default: `[]`

### Setting Environment Variables

To make the configuration more flexible, you can set the configuration values using environment variables. Here are the environment variables you can set:

- `PASSKEYS_USER_MODEL` - the model that will be used to authenticate the user. Default: `App\Models\User`
- `PASSKEYS_ROUTE_PREFIX` - prefix for the 4 routes this package loads. Default: `passkeys`
- `PASSKEYS_ROUTE_MIDDLEWARE` - middleware that will be applied to the routes. Default: `web`
- `PASSKEYS_USERNAME_COLUMN` - the column that will be used to find the user. Default: `email`
- `PASSKEYS_RELYING_PARTY_IDS` - a comma-separated list of domains that will be allowed insecure connection, use with caution. Default: ``
- `PASSKEYS_REGISTRATION_USER_VALIDATION` - a comma-separated list of validation rules that will be applied to the request when registering new user. Default: ``

### Examples

Here are some examples of how to set the environment variables in your `.env` file:

```env
PASSKEYS_USER_MODEL="App\Models\User"
PASSKEYS_ROUTE_PREFIX="passkeys"
PASSKEYS_ROUTE_MIDDLEWARE="web,auth"
PASSKEYS_USERNAME_COLUMN="email"
PASSKEYS_RELYING_PARTY_IDS="example.com,another-example.com"
PASSKEYS_REGISTRATION_USER_VALIDATION="required|string|max:255"
```

## Usage

There are now 6 named routes that make everything work:

`POST 'passkeys.login.start'` - login route, accepts `email` or other field specified in your config. If a user with the given username/email exists and has a passkey registered, credential request options will be returned. If the user does not exist, HTTP 404 will be returned instead.

`POST 'passkeys.login.verify'` - login route, accepts passkey response. If the passkey authentication passes, the user will be logged in. If the passkey authentication fails, an exception with additional information is thrown.

`POST 'passkeys.register.start'` - registration route, accepts `email` or other field specified in your config. Credential request options is returned.

`POST 'passkeys.register.verify'` - registration route, accepts passkey response. If the passkey registration passes, an account will be created from the username/email and any additional data specified in config and sent along with this request. If the passkey registration fails, an exception with additional information is thrown.

`POST 'passkeys.add.options'` - add passkey route, generates options to add a new passkey to a logged-in user.

`POST 'passkeys.add'` - add passkey route, accepts passkey response. If the passkey registration passes, the passkey will be added to the existing account. If the passkey registration fails, an exception with additional information is thrown.

## JS Example
Below is minimal example of how to use this package with js `@simplewebauthn/browser`.
```javascript

import {browserSupportsWebAuthn, startAuthentication, startRegistration} from "@simplewebauthn/browser";
//import 'api' object based on axios and configured with our app url

function base64ToArrayBuffer(base64) {
    const binaryString = atob(base64)
    const len = binaryString.length
    const bytes = new Uint8Array(len)
    for (let i = 0; i < len; i++) {
        bytes[i] = binaryString.charCodeAt(i)
    }
    return bytes.buffer
}
function register() {
    // Ask for the authentication options
    api.post('/passkey/register/options', {
        email: 'your@email.com',
    })
        // Prompt the user to create a passkey
        .then((response) => {
            // Decode the challenge before passing to startRegistration
            if (response.data.challenge) {
                response.data.challenge = base64ToArrayBuffer(response.data.challenge)
            }
            startRegistration(response.data)
        })
        // Verify the data with the server
        .then((attResp) => api.post('/passkey/register', attResp))
        .then((verificationResponse) => {
            if (verificationResponse.data?.verified) {
                // WE ARE REGISTERED AND LOGGED IN / PASSKEY WAS ASSOCIATED WITH NEW OR LOGGED IN ACCOUNT
                return window.location.reload();
            }
            // Something went wrong verifying the registration.
        })
        .catch((error) => {
            // Handle error information
        });
}
function login() {
    // Ask for the authentication options
    api.post('/passkey/login/options', {
            email: 'your@email.com',
        })
        // Prompt the user to authenticate with their passkey
        .then((response) => {
            // Decode the challenge before passing to startRegistration
            if (response.data.challenge) {
                response.data.challenge = base64ToArrayBuffer(response.data.challenge)
            }
            startAuthentication(response.data)
        })
        // Verify the data with the server
        .then((attResp) =>
            api.post('/passkey/login', attResp),
        )
        .then((verificationResponse) => {
            if (verificationResponse.data?.verified) {
                // WE ARE LOGGED IN
                return window.location.reload();
            }
            // Something went wrong verifying the authentication.
        })
        .catch((error) => {
            // Handle error information
        });
}
function addPasskey() {
    // Ask for the options to add a new passkey
    api.post('/passkey/add/options')
        // Prompt the user to create a passkey
        .then((response) => startRegistration(response.data))
        // Verify the data with the server
        .then((attResp) => api.post('/passkey/add', attResp))
        .then((verificationResponse) => {
            if (verificationResponse.data?.verified) {
                // PASSKEY WAS ADDED TO THE EXISTING ACCOUNT
                return window.location.reload();
            }
            // Something went wrong verifying the registration.
        })
        .catch((error) => {
            // Handle error information
        });
}
```

## Refactoring

The code has been refactored to reduce duplication and follow Laravel best practices. A new service class `PasskeyService` has been created to handle common logic for generating options and verifying responses. The `generateOptions` and `verify` methods in `AddPasskeyController`, `RegistrationController`, and `AuthenticationController` have been refactored to use `PasskeyService`.

### PasskeyService

The `PasskeyService` class is located in `src/Services/PasskeyService.php`. It contains the following methods:

- `generateOptions(Request $request, $user = null): array` - Generates options for passkey creation.
- `verify(Request $request, ServerRequestInterface $serverRequest, $user = null): array` - Verifies the passkey response.

### Controllers

The `generateOptions` and `verify` methods in the following controllers have been refactored to use `PasskeyService`:

- `AddPasskeyController`
- `RegistrationController`
- `AuthenticationController`

The `PasskeyService` is injected into the constructors of these controllers and used to handle the common logic for generating options and verifying responses.

### Response Array

The response has been updated to return an array with "verified". This change has been applied to the `verify` methods in the `AddPasskeyController`, `RegistrationController`, and `AuthenticationController`. The updated response is as follows:

```php
if ($response['verified']) {
    return ['verified' => true];
}

return ['verified' => false];
```
