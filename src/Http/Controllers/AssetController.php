<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\Support\Assets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves Sentinel's prebuilt stylesheet and script (assets.mode = "package"):
 * an allow-list of two files, cached for a year when requested with their
 * current content hash (?id=), so no build step is needed to get styled pages.
 */
final readonly class AssetController
{
    public function __construct(private Assets $assets) {}

    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        $path = $this->assets->path($file) ?? throw new NotFoundHttpException;
        $response = new BinaryFileResponse($path, 200, ['Content-Type' => Assets::Files[$file]], true, null, true, false);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if ($request->query('id') === $this->assets->version($file)) {
            $response->setPublic();
            $response->setMaxAge(31536000);
            $response->headers->addCacheControlDirective('immutable');
        } else {
            $response->setPublic();
            $response->setMaxAge(300);
        }

        return $response;
    }
}
