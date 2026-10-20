<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;

/**
 * Typed reads of one configuration array, failing with the full key on a wrong type:
 *
 *     $read = new ConfigReader($config, 'gcego-sentinel');
 *     $read->bool('enabled', true);
 *     $read->section('appearance')->string('layout', 'split');
 */
final readonly class ConfigReader
{
    /**
     * @param  array<array-key, mixed>  $values
     */
    public function __construct(private array $values, private string $prefix) {}

    public function key(string $key): string
    {
        return $this->prefix.'.'.$key;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values) && $this->values[$key] !== null;
    }

    public function section(string $key): self
    {
        $value = $this->values[$key] ?? [];

        return new self(is_array($value) ? $value : throw AuthenticationConfigurationException::invalid($this->key($key), 'must be an array.'), $this->key($key));
    }

    public function bool(string $key, bool $default): bool
    {
        $value = $this->values[$key] ?? $default;

        return is_bool($value) ? $value : throw AuthenticationConfigurationException::invalid($this->key($key), 'must be true or false.');
    }

    public function string(string $key, string $default): string
    {
        $value = $this->values[$key] ?? $default;

        return is_string($value) ? $value : throw AuthenticationConfigurationException::invalid($this->key($key), 'must be a string.');
    }

    /**
     * An integer within [$min, $max].
     */
    public function int(string $key, int $default, int $min = 0, int $max = PHP_INT_MAX): int
    {
        $value = $this->values[$key] ?? $default;

        if (! is_int($value) || $value < $min || $value > $max) {
            throw AuthenticationConfigurationException::invalid($this->key($key), sprintf('must be an integer from %d to %d.', $min, $max));
        }

        return $value;
    }

    /**
     * An integer within [$min, $max], or null.
     */
    public function nullableInt(string $key, int $min = 0, int $max = PHP_INT_MAX): ?int
    {
        return ($this->values[$key] ?? null) === null ? null : $this->int($key, $min, $min, $max);
    }

    /**
     * A rate limit as [attempts, decay seconds].
     *
     * @param  array{0: int, 1: int}  $default
     * @return array{0: int, 1: int}
     */
    public function limit(string $key, array $default): array
    {
        $value = $this->values[$key] ?? $default;

        if (! is_array($value) || ! array_is_list($value) || count($value) !== 2 || ! is_int($value[0]) || ! is_int($value[1]) || $value[0] < 1 || $value[1] < 1 || $value[1] > 86400) {
            throw AuthenticationConfigurationException::invalid($this->key($key), 'must be [attempts, decay seconds] with attempts >= 1 and 1 to 86400 seconds.');
        }

        return [$value[0], $value[1]];
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : throw AuthenticationConfigurationException::invalid($this->key($key), 'must be a string or null.');
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    public function strings(string $key, array $default = []): array
    {
        $value = $this->values[$key] ?? $default;

        if (! is_array($value) || array_filter($value, static fn (mixed $entry): bool => ! is_string($entry)) !== []) {
            throw AuthenticationConfigurationException::invalid($this->key($key), 'must be a list of strings.');
        }

        /** @var list<string> */
        return array_values($value);
    }

    /**
     * @return array<string, string>
     */
    public function stringMap(string $key): array
    {
        $value = $this->values[$key] ?? [];
        $map = [];

        if (! is_array($value)) {
            throw AuthenticationConfigurationException::invalid($this->key($key), 'must be an array of strings keyed by name.');
        }

        foreach ($value as $name => $entry) {
            if (! is_string($name) || ! is_string($entry)) {
                throw AuthenticationConfigurationException::invalid($this->key($key), 'must be an array of strings keyed by name.');
            }

            $map[$name] = $entry;
        }

        return $map;
    }

    /**
     * @return array<string, array<array-key, mixed>>
     */
    public function sections(string $key): array
    {
        $value = $this->values[$key] ?? [];
        $sections = [];

        if (! is_array($value)) {
            throw AuthenticationConfigurationException::invalid($this->key($key), 'must be an array keyed by name.');
        }

        foreach ($value as $name => $entry) {
            if (! is_string($name) || ! is_array($entry)) {
                throw AuthenticationConfigurationException::invalid($this->key($key), 'must be an array of arrays keyed by name.');
            }

            $sections[$name] = $entry;
        }

        return $sections;
    }

    /**
     * A class name that exists and implements $contract, or null.
     *
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return class-string<T>|null
     */
    public function implementation(string $key, string $contract): ?string
    {
        $class = $this->nullableString($key);

        if ($class === null) {
            return null;
        }

        if (! is_a($class, $contract, true)) {
            throw AuthenticationConfigurationException::invalid($this->key($key), sprintf('%s must implement %s.', $class, $contract));
        }

        return $class;
    }
}
