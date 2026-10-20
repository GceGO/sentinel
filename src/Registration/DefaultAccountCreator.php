<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Registration;

use Gcego\Sentinel\Authentication\Identifier;
use Gcego\Sentinel\Contracts\CreatesAccounts;
use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\Passwords\PasswordPolicy;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Creates the guard's user model with a name, an email address and a
 * hashed password, nothing else: no role, no status, no extra field (bind
 * your own CreatesAccounts to store configured registration fields).
 */
final readonly class DefaultAccountCreator implements CreatesAccounts
{
    public function __construct(private SentinelConfig $config, private Repository $settings, private PasswordPolicy $policy, private Hasher $hasher) {}

    public function create(array $input, ?AuthenticationPanel $panel = null): Authenticatable
    {
        $model = $this->model();
        $input['email'] = Identifier::normalize($input['email'] ?? '', $this->config->lowercaseIdentifier);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($model->getTable(), 'email')],
            'password' => $this->policy->rules(),
        ])->validate();

        $user = $model->newInstance();
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            $user instanceof Authenticatable ? $user->getAuthPasswordName() : 'password' => $this->hasher->make($input['password']),
        ])->save();

        return $user instanceof Authenticatable ? $user : throw AuthenticationConfigurationException::invalid('auth.providers.users.model', 'the user model must implement Authenticatable.');
    }

    private function model(): Model
    {
        $provider = $this->settings->get('auth.guards.'.$this->config->guard.'.provider');
        $class = is_string($provider) ? $this->settings->get('auth.providers.'.$provider.'.model') : null;

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw AuthenticationConfigurationException::invalid('auth.providers', 'registration needs an Eloquent user model (or bind your own Contracts\\CreatesAccounts).');
        }

        return new $class;
    }
}
