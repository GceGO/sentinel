<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Livewire;

use Gcego\Sentinel\Livewire\Concerns\InteractsWithAccount;
use Gcego\Sentinel\Passwords\PasswordChanger;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Change password through Sentinel's PasswordChanger (current password,
 * password policy, other sessions ended, owner notified). The three
 * password fields are emptied before every response, so they are never
 * stored in the component snapshot.
 */
final class UpdatePasswordForm extends Component
{
    use InteractsWithAccount;

    /**
     * @var array{current_password: string, password: string, password_confirmation: string}
     */
    public array $state = ['current_password' => '', 'password' => '', 'password_confirmation' => ''];

    public bool $saved = false;

    public function mount(): void
    {
        if (! app(SentinelConfig::class)->passwordUpdate) {
            throw new NotFoundHttpException;
        }
    }

    public function updatePassword(PasswordChanger $passwords): void
    {
        $this->resetErrorBag();
        $this->saved = false;

        try {
            $passwords->change($this->account(), [
                'current_password' => (string) $this->state['current_password'],
                'password' => (string) $this->state['password'],
                'password_confirmation' => (string) $this->state['password_confirmation'],
            ], $this->currentSession());
        } finally {
            $this->clearPasswords();
        }

        $this->saved = true;
    }

    public function dehydrate(): void
    {
        $this->clearPasswords();
    }

    public function render(): View
    {
        return view('gcego-sentinel::livewire.update-password');
    }

    private function clearPasswords(): void
    {
        $this->state = ['current_password' => '', 'password' => '', 'password_confirmation' => ''];
    }
}
