# Registration

Registration needs both of the following:

1. Sentinel's page switch (`gcego-sentinel.pages.register`, on by default);
2. the `RegistrationPolicy` allowing it.

By default the policy (`ConfiguredRegistrationPolicy`) is:

| Where | Default |
| --- | --- |
| The public site (`/register`) | `gcego-sentinel.registration.enabled`, **false** |
| A panel (`/{panel}/register`) | The panel's `registration` flag, **false** |

The policy is enforced server-side. `EnsureRegistrationIsAllowed` is on both the `register` and `register.store` routes, so posting to the endpoint without an allowed page is a 404 too. `register.store` is also throttled per client.

```php
'registration' => [
    'enabled' => true,
    'terms_url' => '/terms',   // a "terms" checkbox, required and checked server-side
    'creator' => \App\Auth\CreateAccount::class,  // optional Contracts\CreatesAccounts
    'fields' => [              // extra inputs, passed to the account creator
        'school' => ['label' => 'School', 'autocomplete' => 'organization', 'required' => true],
    ],
],
```

## Creating the account

`Contracts\CreatesAccounts::create(array $input, ?AuthenticationPanel $panel)` runs inside a transaction. It receives only the built-in fields (name, email, password, password_confirmation) and the configured extra fields; anything else posted is ignored.

The default `DefaultAccountCreator`:
- validates the name, a unique email and the password policy;
- stores name, email and the hashed password, and nothing else.

To store extra fields, bind your own creator. Reuse `PasswordPolicy::rules()` for the password.

Afterwards Sentinel dispatches Laravel's `Registered` event (which sends the verification link) and `Events\RegistrationCompleted`, then signs the new account in.

## Policies for different audiences

Bind your own policy through `registration.policy` (a class implementing `Contracts\RegistrationPolicy`):

```php
final class InvitationPolicy implements RegistrationPolicy
{
    public function allows(Request $request, ?AuthenticationPanel $panel = null): bool
    {
        return match ($panel?->id) {
            'student' => true,                                         // public student sign-up
            'teacher', 'institution' => Invitation::validFor($request), // staff and institutions by invitation
            default => false,                                          // never for administration
        };
    }
}
```

## No privileged roles

Sentinel never assigns roles or permissions. A new account is exactly what your `CreatesAccounts` implementation creates.

After registering on a panel's page, the user is sent into that panel only if gcego/security already allows it. Otherwise they go to the verification notice, or `gcego-sentinel.home`.

## Known limitation

Registration tells the visitor when an email address is already taken. It is off by default and throttled. Use an invitation policy if this matters.
