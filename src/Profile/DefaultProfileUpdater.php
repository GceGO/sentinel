<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Profile;

use Gcego\Sentinel\Authentication\Identifier;
use Gcego\Sentinel\Contracts\UpdatesProfiles;
use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Saves the configured profile fields only. "name" and "email" are
 * validated (the email unique among accounts); other fields as optional
 * strings. A new email address clears email_verified_at and sends a new
 * verification link when the model verifies email.
 */
final readonly class DefaultProfileUpdater implements UpdatesProfiles
{
    public function __construct(private SentinelConfig $config) {}

    public function update(Authenticatable $user, array $input): void
    {
        if (! $user instanceof Model) {
            throw AuthenticationConfigurationException::invalid('auth.providers.users.model', 'the profile form needs an Eloquent user model (or bind your own Contracts\\UpdatesProfiles).');
        }

        $rules = [];

        foreach ($this->config->profileFields as $field) {
            $rules[$field->name] = match ($field->name) {
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique($user->getTable(), 'email')->ignore($user->getKey(), $user->getKeyName())],
                default => [$field->required ? 'required' : 'nullable', 'string', 'max:255'],
            };
        }

        if (isset($input['email'])) {
            $input['email'] = Identifier::normalize($input['email'], $this->config->lowercaseIdentifier);
        }

        /** @var array<string, mixed> $values */
        $values = Validator::make(array_intersect_key($input, $rules), $rules)->validateWithBag('updateProfileInformation');
        $emailChanged = array_key_exists('email', $values) && $values['email'] !== data_get($user, 'email');

        $user->forceFill($values);

        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        if ($emailChanged && $user instanceof MustVerifyEmail && $this->config->emailVerification) {
            $user->sendEmailVerificationNotification();
        }
    }
}
