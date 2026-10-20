<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Livewire;

use Gcego\Sentinel\Contracts\UpdatesProfiles;
use Gcego\Sentinel\Events\EmailChanged;
use Gcego\Sentinel\Livewire\Concerns\ConfirmsPasswords;
use Gcego\Sentinel\Livewire\Concerns\InteractsWithAccount;
use Gcego\Sentinel\Notifications\SecurityNotifier;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Support\FormField;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Profile fields (gcego-sentinel.profile.fields) saved through
 * Contracts\UpdatesProfiles, which validates them and starts
 * re-verification when the email changes. Changing the email address (the
 * sign-in identifier) needs a recent password confirmation first, and the
 * previous address is told about the change.
 */
final class ProfileInformationForm extends Component
{
    use ConfirmsPasswords;
    use InteractsWithAccount;

    /**
     * @var array<string, string>
     */
    public array $state = [];

    public bool $saved = false;

    public bool $verificationSent = false;

    public function mount(): void
    {
        if (! app(SentinelConfig::class)->profile) {
            throw new NotFoundHttpException;
        }

        $this->fill(['state' => $this->current()]);
    }

    public function updateProfileInformation(UpdatesProfiles $updater, SecurityNotifier $notifier): void
    {
        $this->resetErrorBag();
        $this->saved = false;
        $this->verificationSent = false;
        $user = $this->account();
        $state = array_intersect_key(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $this->state), $this->current());
        $current = data_get($user, 'email');
        $previous = is_string($current) ? $current : '';
        $emailChanged = isset($state['email']) && mb_strtolower(trim($state['email'])) !== mb_strtolower($previous);

        if ($emailChanged && ! $this->passwordIsConfirmed()) {
            $this->startConfirmingPassword('updateProfileInformation');

            return;
        }

        $updater->update($user, $state);
        $fresh = $user instanceof Model ? ($user->fresh() ?? $user) : $user;
        $this->state = $this->current();
        $this->saved = true;
        $emailChanged = $emailChanged && data_get($fresh, 'email') !== $previous;
        $this->verificationSent = $emailChanged && $fresh instanceof MustVerifyEmail && ! $fresh->hasVerifiedEmail();

        if ($emailChanged) {
            event(new EmailChanged(UserKey::of($user), $this->verificationSent));
            $notifier->sendToAddress($previous, 'email_changed');
        }
    }

    public function render(): View
    {
        return view('gcego-sentinel::livewire.profile-information', ['fields' => app(SentinelConfig::class)->profileFields]);
    }

    protected function confirmableActions(): array
    {
        return ['updateProfileInformation'];
    }

    /**
     * The user's current values of the configured fields.
     *
     * @return array<string, string>
     */
    private function current(): array
    {
        $user = $this->account();
        $values = [];

        foreach (app(SentinelConfig::class)->profileFields as $field) {
            /** @var FormField $field */
            $value = data_get($user, $field->name);
            $values[$field->name] = is_scalar($value) ? (string) $value : '';
        }

        return $values;
    }
}
