<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Branding;

use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\Support\ConfigReader;
use Gcego\Sentinel\Support\SafeUrl;
use Gcego\Swatches\Accessibility\Contrast;
use Gcego\Swatches\Branding\Brand;
use Gcego\Swatches\Branding\BrandCompiler;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Generators\CssGenerator;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Themes\Theme;
use Gcego\Swatches\Themes\ThemeRegistry;
use Illuminate\Container\Container;

/**
 * Trusted, validated branding of an authentication page. Every URL is
 * relative or https (links may also be http or mailto:), colors are hex and
 * text is rendered escaped, so no branding value can inject markup.
 *
 * Layers merge (configuration < panel < tenant): a later non-null value wins.
 */
final readonly class AuthenticationBranding
{
    /**
     * @param  array<string, string>  $legal  label => URL
     */
    public function __construct(
        public ?string $name = null,
        public ?string $tagline = null,
        public ?string $logo = null,
        public ?string $logoDark = null,
        public ?string $favicon = null,
        public ?string $background = null,
        public ?string $illustration = null,
        public ?string $accent = null,
        public ?string $footer = null,
        public array $legal = [],
        public ?string $supportUrl = null,
        public ?string $supportEmail = null,
    ) {
        foreach (['logo' => $logo, 'logo_dark' => $logoDark, 'favicon' => $favicon, 'background' => $background, 'illustration' => $illustration] as $key => $url) {
            if ($url !== null && SafeUrl::asset($url) === null) {
                throw AuthenticationConfigurationException::invalid('branding.'.$key, 'use a root-relative path ("/images/logo.svg") or an https URL.');
            }
        }

        if ($accent !== null && SafeUrl::color($accent) === null) {
            throw AuthenticationConfigurationException::invalid('branding.accent', 'use a hex color such as "#2563eb".');
        }

        foreach ($legal as $label => $url) {
            if ($label === '' || SafeUrl::link($url) === null) {
                throw AuthenticationConfigurationException::invalid('branding.legal', sprintf('"%s" needs a label and a relative, http(s) or mailto: URL.', mb_substr($label, 0, 60)));
            }
        }

        if ($supportUrl !== null && SafeUrl::link($supportUrl) === null) {
            throw AuthenticationConfigurationException::invalid('branding.support_url', 'use a relative, http(s) or mailto: URL.');
        }

        if ($supportEmail !== null && filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw AuthenticationConfigurationException::invalid('branding.support_email', 'use an email address.');
        }

        foreach (['name' => $name, 'tagline' => $tagline, 'footer' => $footer] as $key => $text) {
            if ($text !== null && mb_strlen($text) > 500) {
                throw AuthenticationConfigurationException::invalid('branding.'.$key, 'keep it under 500 characters.');
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(array $values, string $key = 'gcego-sentinel.branding'): self
    {
        $read = new ConfigReader($values, $key);

        try {
            return new self(
                $read->nullableString('name'),
                $read->nullableString('tagline'),
                $read->nullableString('logo'),
                $read->nullableString('logo_dark'),
                $read->nullableString('favicon'),
                $read->nullableString('background'),
                $read->nullableString('illustration'),
                ($accent = $read->nullableString('accent')) === null ? null : (SafeUrl::color($accent) ?? $accent),
                $read->nullableString('footer'),
                $read->stringMap('legal'),
                $read->nullableString('support_url'),
                $read->nullableString('support_email'),
            );
        } catch (AuthenticationConfigurationException $exception) {
            throw new AuthenticationConfigurationException(str_replace('branding.', $key.'.', $exception->getMessage()), previous: $exception);
        }
    }

    /**
     * This branding with every non-empty value of $other on top.
     */
    public function merge(self $other): self
    {
        return new self(
            $other->name ?? $this->name,
            $other->tagline ?? $this->tagline,
            $other->logo ?? $this->logo,
            $other->logoDark ?? $this->logoDark,
            $other->favicon ?? $this->favicon,
            $other->background ?? $this->background,
            $other->illustration ?? $this->illustration,
            $other->accent ?? $this->accent,
            $other->footer ?? $this->footer,
            $other->legal === [] ? $this->legal : $other->legal,
            $other->supportUrl ?? $this->supportUrl,
            $other->supportEmail ?? $this->supportEmail,
        );
    }

    public function withName(string $name): self
    {
        return $this->merge(new self(name: $name));
    }

    /**
     * Text color for content on the accent color: whichever of white or near-black contrasts more.
     */
    public function accentForeground(): ?string
    {
        if ($this->accent === null) {
            return null;
        }

        return Contrast::ratio('#ffffff', $this->accent) >= Contrast::ratio('#0f172a', $this->accent) ? '#ffffff' : '#0f172a';
    }

    /**
     * The custom properties the accent sets, compiled by gcego/swatches into accessible
     * light and dark primary tokens (4.5:1 for text, labels and tints in both modes;
     * an accent that is too light for white surfaces is darkened, never used as is).
     */
    public function css(): string
    {
        if ($this->accent === null) {
            return '';
        }

        $brand = new Brand(primary: Color::parse($this->accent));
        $container = Container::getInstance();

        if ($container->bound(SwatchesManager::class)) {
            return $container->make(SwatchesManager::class)->brandCss($brand, standalone: true);
        }

        $compiled = (new BrandCompiler)->compile($brand, ThemeRegistry::withDefaults()->resolve(Theme::Base));

        return (new CssGenerator)->brand($compiled->light, $compiled->dark, ':root');
    }

    /**
     * Initials of the brand name for the default mark ("GCEGO Studio" -> "GS").
     */
    public function initials(): string
    {
        $words = preg_split('/[\s\-_]+/u', trim((string) $this->name)) ?: [];
        $initials = '';

        foreach (array_slice(array_values(array_filter($words, static fn (string $word): bool => $word !== '')), 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials === '' ? 'G' : $initials;
    }
}
