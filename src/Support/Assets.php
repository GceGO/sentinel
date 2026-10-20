<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\HtmlString;

/**
 * The stylesheet and script of Sentinel pages: the prebuilt files in
 * resources/dist (served by AssetController, versioned by content hash),
 * or the application's Vite entry points (assets.mode = "vite"). Both are
 * external files, so pages work under a Content Security Policy without
 * inline scripts.
 */
final class Assets
{
    public const array Files = [
        'sentinel.css' => 'text/css; charset=UTF-8',
        'sentinel.js' => 'text/javascript; charset=UTF-8',
    ];

    /**
     * @var array<string, string>
     */
    private array $versions = [];

    public function __construct(
        private readonly SentinelConfig $config,
        private readonly UrlGenerator $urls,
        private readonly Vite $vite,
    ) {}

    public static function directory(): string
    {
        return dirname(__DIR__, 2).'/resources/dist';
    }

    public function path(string $file): ?string
    {
        if (! isset(self::Files[$file])) {
            return null;
        }

        $path = self::directory().'/'.$file;

        return is_file($path) ? $path : null;
    }

    public function version(string $file): string
    {
        if (! isset($this->versions[$file])) {
            $path = $this->path($file);
            $this->versions[$file] = $path === null ? '' : substr((string) hash_file('xxh128', $path), 0, 16);
        }

        return $this->versions[$file];
    }

    public function url(string $file): ?string
    {
        if ($this->path($file) === null || ! Routes::has(SentinelRoutes::Assets)) {
            return null;
        }

        return $this->urls->route(SentinelRoutes::Assets, ['file' => $file, 'id' => $this->version($file)]);
    }

    public function stylesheet(): HtmlString
    {
        if ($this->config->assetsMode === 'vite') {
            return new HtmlString($this->vite->__invoke($this->config->vite)->toHtml());
        }

        $url = $this->url('sentinel.css');

        return new HtmlString($url === null ? '' : '<link rel="stylesheet" href="'.e($url).'">');
    }

    /**
     * The script, loaded in <head> without defer so the appearance (dark
     * mode) is applied before the first paint; the rest of it waits for
     * DOMContentLoaded.
     */
    public function script(): HtmlString
    {
        if ($this->config->assetsMode === 'vite') {
            return new HtmlString('');
        }

        $url = $this->url('sentinel.js');
        $nonce = $this->vite->cspNonce();

        return new HtmlString($url === null ? '' : '<script src="'.e($url).'"'.($nonce === null ? '' : ' nonce="'.e($nonce).'"').'></script>');
    }
}
