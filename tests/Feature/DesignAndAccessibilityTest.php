<?php

declare(strict_types=1);

use Gcego\Sentinel\Contracts\TenantBrandingProvider;
use Gcego\Sentinel\Support\Assets;
use Gcego\Sentinel\Tests\Fixtures\TenantBranding;
use Illuminate\Support\Facades\Vite;

/**
 * @return array{document: DOMDocument, xpath: DOMXPath}
 */
function dom(string $html): array
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return ['document' => $document, 'xpath' => new DOMXPath($document)];
}

/**
 * Basic WCAG checks on a rendered page: language, one h1, a main landmark,
 * a skip link to it, a label for every visible input, and ARIA references
 * that point at existing elements.
 *
 * @return list<string> problems
 */
function accessibilityProblems(string $html): array
{
    ['xpath' => $xpath] = dom($html);
    $problems = [];

    if ($xpath->query('//html[@lang]')->length !== 1) {
        $problems[] = 'html lang';
    }

    if ($xpath->query('//h1')->length !== 1) {
        $problems[] = 'one h1 ('.$xpath->query('//h1')->length.')';
    }

    if ($xpath->query('//main[@id="sentinel-main"]')->length !== 1 || $xpath->query('//a[@href="#sentinel-main"]')->length !== 1) {
        $problems[] = 'main landmark and skip link';
    }

    foreach ($xpath->query('//input[not(@type="hidden")]') as $input) {
        $id = $input->getAttribute('id');

        if ($id === '' || $xpath->query('//label[@for="'.$id.'"]')->length !== 1) {
            $problems[] = 'label for '.$input->getAttribute('name');
        }
    }

    foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby or @aria-controls]') as $element) {
        foreach (['aria-describedby', 'aria-labelledby', 'aria-controls'] as $attribute) {
            foreach (array_filter(explode(' ', $element->getAttribute($attribute))) as $reference) {
                if ($xpath->query('//*[@id="'.$reference.'"]')->length !== 1) {
                    $problems[] = $attribute.' '.$reference;
                }
            }
        }
    }

    foreach ($xpath->query('//button') as $button) {
        if (trim($button->textContent) === '' && $button->getAttribute('aria-label') === '') {
            $problems[] = 'button without a name';
        }
    }

    foreach ($xpath->query('//img[not(@alt)]') as $image) {
        $problems[] = 'img without alt';
    }

    return $problems;
}

it('renders accessible markup on every authentication page', function (string $url, ?Closure $prepare = null): void {
    $prepare?->call($this);

    expect(accessibilityProblems((string) $this->get($url)->assertOk()->getContent()))->toBe([]);
})->with([
    'login' => ['/login'],
    'panel login' => ['/admin/login'],
    'tenant login' => ['/institution/1/login'],
    'forgot password' => ['/forgot-password'],
    'reset password' => ['/reset-password/token?email=a@example.test'],
    'session expired' => ['/session-expired'],
    'signed out' => ['/signed-out'],
    'account unavailable' => ['/account/unavailable'],
    'register' => ['/student/register'],
    'verify email' => ['/email/verify', function (): void {
        $this->actingAs(user(['email_verified_at' => null]));
    }],
    'confirm password' => ['/user/confirm-password', function (): void {
        $this->actingAs(user());
    }],
    'account security' => ['/account/security', function (): void {
        $this->actingAs(user());
    }],
]);

it('associates errors with their fields and announces them', function (): void {
    $this->from('/login')->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong']);
    ['xpath' => $xpath] = dom((string) $this->get('/login')->getContent());

    $email = $xpath->query('//input[@name="email"]')->item(0);
    expect($email->getAttribute('aria-invalid'))->toBe('true')
        ->and($email->getAttribute('aria-describedby'))->toBe('sentinel-email-error')
        ->and($xpath->query('//*[@id="sentinel-email-error"]')->item(0)?->textContent)->toContain(__('auth.failed'))
        ->and($xpath->query('//*[@role="alert"][@data-sentinel-error-summary][@tabindex="-1"]')->length)->toBe(1);
});

it('renders the same form in all four layouts', function (string $layout, string $marker): void {
    $this->reboot(['gcego-sentinel.appearance.layout' => $layout, 'gcego-sentinel.branding.background' => '/images/campus.jpg']);

    $html = (string) $this->get('/login')->getContent();

    expect($html)->toContain($marker)
        ->toContain('action="http://localhost/login"')
        ->toContain('name="email"')
        ->toContain('name="password"');
})->with([
    'centered' => ['centered', 'data-sentinel-layout="centered"'],
    'split' => ['split', 'data-sentinel-layout="split"'],
    'minimal' => ['minimal', 'data-sentinel-layout="minimal"'],
    'branded' => ['branded', '<img src="/images/campus.jpg" alt=""'],
]);

it('applies light, dark and system appearance', function (): void {
    $this->reboot(['gcego-sentinel.appearance.theme' => 'dark']);
    expect((string) $this->get('/login')->getContent())->toContain('class="dark" data-sentinel-theme="dark"')->toContain('content="dark"');

    $this->reboot(['gcego-sentinel.appearance.theme' => 'light', 'gcego-sentinel.panels.admin' => ['path' => 'admin', 'theme' => 'dark']]);
    expect((string) $this->get('/login')->getContent())->toContain('data-sentinel-theme="light"')->toContain('content="light"');
    expect((string) $this->get('/admin/login')->getContent())->toContain('data-sentinel-theme="dark"');

    $this->reboot([]);
    $html = (string) $this->get('/login')->getContent();
    expect($html)->toContain('data-sentinel-theme="system"')->toContain('content="light dark"')->toContain('data-sentinel-appearance-option="dark"');
});

it('brands pages from configuration, panels and tenants with validated values', function (): void {
    $this->reboot([
        'gcego-sentinel.branding' => ['name' => 'GCEGO', 'logo' => '/images/gcego.svg', 'accent' => '#1d4ed8', 'legal' => ['Privacy' => '/privacy'], 'support_email' => 'help@gcego.test', 'footer' => 'GCEGO Cameroon'],
        'gcego-sentinel.panels.teacher' => ['path' => 'teacher', 'title' => 'Teachers', 'permission' => 'panels.teacher', 'branding' => ['accent' => '#b45309']],
    ]);
    app()->singleton(TenantBrandingProvider::class, TenantBranding::class);

    $site = (string) $this->get('/login')->getContent();
    expect($site)->toContain('<img src="/images/gcego.svg" alt="GCEGO"')
        ->toContain('--gcego-primary:#1d4ed8')
        ->toContain('href="/privacy"')
        ->toContain('href="mailto:help@gcego.test"')
        ->toContain('GCEGO Cameroon');

    expect((string) $this->get('/teacher/login')->getContent())->toContain('--gcego-primary:#b45309');

    $tenant = (string) $this->get('/institution/1/login')->getContent();
    expect($tenant)->toContain('Lycée Bilingue de Buea')->toContain('/images/lbb.svg')->toContain('--gcego-primary:#047857');
});

it('lets applications override a page view and add layouts', function (): void {
    $this->reboot([
        'gcego-sentinel.views' => ['login' => 'fixtures::login'],
        'gcego-sentinel.layouts' => ['school' => 'fixtures::school-layout'],
        'gcego-sentinel.appearance.layout' => 'school',
    ]);
    view()->addNamespace('fixtures', dirname(__DIR__).'/Fixtures/views');

    $html = (string) $this->get('/login')->getContent();

    expect($html)->toContain('Custom sign-in for GCEGO Studio')->toContain('data-layout="school"');
});

it('serves the prebuilt assets with long-lived caching for the current version only', function (): void {
    $assets = app(Assets::class);
    $url = parse_url((string) $assets->url('sentinel.css'), PHP_URL_PATH).'?'.parse_url((string) $assets->url('sentinel.css'), PHP_URL_QUERY);

    $current = $this->get($url);
    $current->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect((string) $current->headers->get('Cache-Control'))->toContain('immutable')->toContain('max-age=31536000');

    $stale = $this->get('/gcego-sentinel/assets/sentinel.js?id=old');
    $stale->assertOk();
    expect((string) $stale->headers->get('Cache-Control'))->not->toContain('immutable');

    $this->get('/gcego-sentinel/assets/secret.env')->assertNotFound();
    $this->get('/gcego-sentinel/assets/..%2F..%2Fcomposer.json')->assertNotFound();
});

it('uses the application\'s Vite build in vite mode', function (): void {
    $this->reboot(['gcego-sentinel.assets.mode' => 'vite', 'gcego-sentinel.assets.vite' => ['vendor/gcego/sentinel/resources/css/sentinel.css', 'vendor/gcego/sentinel/resources/js/sentinel.js']]);
    $hot = sys_get_temp_dir().'/gcego-sentinel-'.uniqid().'.hot';
    file_put_contents($hot, 'http://localhost:5173');
    Vite::useHotFile($hot);

    try {
        $html = (string) $this->get('/login')->getContent();
    } finally {
        @unlink($hot);
    }

    expect($html)->toContain('http://localhost:5173/vendor/gcego/sentinel/resources/css/sentinel.css')->not->toContain('/gcego-sentinel/assets/');
});

it('keeps pages usable without JavaScript: enhancements start hidden', function (): void {
    ['xpath' => $xpath] = dom((string) $this->get('/login')->getContent());

    foreach ($xpath->query('//*[@data-sentinel-js]') as $element) {
        expect($element->hasAttribute('hidden'))->toBeTrue();
    }

    expect($xpath->query('//script[not(@src)]')->length)->toBe(0);
});
