<?php

declare(strict_types=1);

namespace Gcego\Sentinel;

use Gcego\Core\Diagnostics\DiagnosticReport;
use Gcego\Sentinel\Branding\AuthenticationBranding;
use Gcego\Sentinel\Context\AuthenticationContext;
use Gcego\Sentinel\Contracts\AuthenticationBrandingResolver;
use Gcego\Sentinel\Contracts\AuthenticationContextResolver;
use Gcego\Sentinel\Contracts\AuthenticationFeatureResolver;
use Gcego\Sentinel\Contracts\AuthenticationLayout;
use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Diagnostics\AuthenticationHealthCheck;
use Gcego\Sentinel\Diagnostics\SentinelInspector;
use Gcego\Sentinel\Exceptions\AuthenticationFeatureUnavailableException;
use Gcego\Sentinel\Features\Feature;
use Gcego\Sentinel\Layouts\LayoutManager;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The entry point of gcego/sentinel (no facade): configuration, features,
 * pages, layouts, branding, panels and redirects. Credentials are checked
 * by the Authentication services (CredentialAuthenticator, LoginPipeline)
 * through Laravel's guards and user providers.
 *
 *     $sentinel = app(SentinelManager::class);
 *
 *     $sentinel->enabled(Feature::TwoFactorAuthentication);   // the switch AND its backend
 *     $sentinel->page($request, AuthenticationPage::Login);    // the rendered page response
 *     $sentinel->loginUrl($request);                           // "/admin/login" inside the admin panel
 *     $sentinel->layouts()->register(new ViewLayout('school', 'auth.school'));
 *
 * Services are resolved per call (request-scoped ones included), so the
 * manager is safe in long-running workers.
 */
final readonly class SentinelManager
{
    public function __construct(private Container $container) {}

    public function config(): SentinelConfig
    {
        return $this->container->make(SentinelConfig::class);
    }

    public function guardName(): string
    {
        return $this->config()->guard;
    }

    public function guard(): StatefulGuard
    {
        $guard = $this->container->make(AuthFactory::class)->guard($this->guardName());

        return $guard instanceof StatefulGuard ? $guard : throw new Exceptions\AuthenticationConfigurationException(sprintf('The "%s" guard is not a session (stateful) guard; Sentinel needs one.', $this->guardName()));
    }

    /**
     * The guard's user provider (it retrieves users and checks passwords through Laravel's hasher).
     */
    public function provider(): UserProvider
    {
        $guard = $this->guard();
        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;

        return $provider instanceof UserProvider ? $provider : throw new Exceptions\AuthenticationConfigurationException(sprintf('The "%s" guard has no user provider.', $this->guardName()));
    }

    public function features(): AuthenticationFeatureResolver
    {
        return $this->container->make(AuthenticationFeatureResolver::class);
    }

    public function enabled(Feature $feature, ?AuthenticationPanel $panel = null): bool
    {
        return $this->features()->enabled($feature, $panel);
    }

    public function panels(): AuthenticationPanels
    {
        return $this->container->make(AuthenticationPanels::class);
    }

    public function layouts(): LayoutManager
    {
        return $this->container->make(LayoutManager::class);
    }

    public function layout(?string $name = null): AuthenticationLayout
    {
        return $this->layouts()->get($name);
    }

    public function branding(?AuthenticationPanel $panel = null): AuthenticationBranding
    {
        return $this->container->make(AuthenticationBrandingResolver::class)->resolve($panel);
    }

    public function redirects(): AuthenticationRedirectResolver
    {
        return $this->container->make(AuthenticationRedirectResolver::class);
    }

    public function context(Request $request, AuthenticationPage $page): AuthenticationContext
    {
        return $this->container->make(AuthenticationContextResolver::class)->resolve($request, $page);
    }

    /**
     * The Blade view of a page: the gcego-sentinel.views override, or Sentinel's.
     */
    public function view(AuthenticationPage $page): string
    {
        return $this->config()->views[$page->value] ?? $page->view();
    }

    /**
     * Render a page as a response (never cached by browsers or proxies).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthenticationFeatureUnavailableException when the page or its feature is unavailable.
     */
    public function page(Request $request, AuthenticationPage $page, array $data = []): Response
    {
        $context = $this->context($request, $page);

        if (! $this->features()->page($page, $context->panel)) {
            throw AuthenticationFeatureUnavailableException::for($page->feature() ?? Feature::Login);
        }

        $views = $this->container->make(ViewFactory::class);
        $view = $this->view($page);

        if (! $views->exists($view)) {
            throw Exceptions\AuthenticationConfigurationException::invalid(SentinelConfig::Key.'.views.'.$page->value, sprintf('the view "%s" does not exist.', $view));
        }

        $response = new Response($views->make($view, [...$data, 'context' => $context])->render());
        $response->headers->set('Cache-Control', 'no-store, private');

        // The reset link's token is in the URL: never send it to other origins in a Referer header.
        $response->headers->set('Referrer-Policy', $page === AuthenticationPage::ResetPassword ? 'no-referrer' : 'same-origin');

        return $response;
    }

    /**
     * The login page for a request ("/admin/login" for paths inside the
     * admin panel): use it for unauthenticated redirects,
     * `$middleware->redirectGuestsTo(fn (Request $request) => app(SentinelManager::class)->loginUrl($request))`.
     */
    public function loginUrl(Request $request): string
    {
        return $this->redirects()->login($request);
    }

    public function inspect(): DiagnosticReport
    {
        return $this->container->make(SentinelInspector::class)->inspect();
    }

    public function health(): AuthenticationHealthCheck
    {
        return $this->container->make(AuthenticationHealthCheck::class);
    }
}
