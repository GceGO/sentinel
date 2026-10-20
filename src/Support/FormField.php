<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;

/**
 * A configured input of the registration or profile form. The value is
 * passed to the CreatesAccounts / UpdatesProfiles implementation, which validates it.
 */
final readonly class FormField
{
    public const array Types = ['text', 'email', 'tel', 'url', 'number', 'date'];

    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public ?string $autocomplete = null,
        public bool $required = false,
        public ?string $help = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) !== 1) {
            throw AuthenticationConfigurationException::invalid('field "'.mb_substr($name, 0, 60).'"', 'use a lowercase input name such as "phone".');
        }

        if (! in_array($type, self::Types, true)) {
            throw AuthenticationConfigurationException::invalid('field "'.$name.'".type', 'use '.implode(', ', self::Types).'.');
        }

        if ($autocomplete !== null && preg_match('/^[a-z0-9 -]{1,100}$/', $autocomplete) !== 1) {
            throw AuthenticationConfigurationException::invalid('field "'.$name.'".autocomplete', 'use an autocomplete token such as "tel".');
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(string $name, array $values, string $key): self
    {
        $read = new ConfigReader($values, $key.'.'.$name);

        return new self(
            $name,
            $read->string('label', $name),
            $read->string('type', 'text'),
            $read->nullableString('autocomplete'),
            $read->bool('required', false),
            $read->nullableString('help'),
        );
    }

    /**
     * The label, translated when it is a translation key.
     */
    public function label(): string
    {
        $label = __($this->label);

        return is_string($label) ? $label : $this->label;
    }

    public function help(): ?string
    {
        if ($this->help === null) {
            return null;
        }

        $help = __($this->help);

        return is_string($help) ? $help : $this->help;
    }
}
