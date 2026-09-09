<?php

namespace Flexpik\FilamentStudio\Api;

use Flexpik\FilamentStudio\Api\Middleware\ValidateApiKey;
use Illuminate\Support\Facades\Route;

class StudioApiRouteRegistrar
{
    /**
     * Path segments that belong to sibling APIs under the same prefix.
     *
     * The collection routes are a one- and two-segment catch-all, so without this
     * they match /api/studio/flows and /api/studio/webhooks/{slug} too — and being
     * registered first, they won. Which API answered then depended on boot order.
     */
    public const RESERVED_SEGMENTS = ['flows', 'webhooks'];

    public static function register(): void
    {
        $prefix = config('filament-studio.api.prefix', 'api/studio');
        // The lookahead must end at a segment boundary, not at end-of-subject:
        // the pattern is spliced into the regex for the whole path, so a bare `$`
        // would let "webhooks" through on /api/studio/webhooks/{slug}.
        $slugPattern = '(?!(?:'.implode('|', self::RESERVED_SEGMENTS).')(?:/|$))[^/]+';

        Route::prefix($prefix)
            ->middleware(['api', 'throttle:studio-api'])
            ->group(function () use ($slugPattern) {
                Route::get('{collection_slug}', [StudioApiController::class, 'index'])
                    ->where('collection_slug', $slugPattern)
                    ->middleware(ValidateApiKey::class.':index');

                Route::get('{collection_slug}/{uuid}', [StudioApiController::class, 'show'])
                    ->where('collection_slug', $slugPattern)
                    ->middleware(ValidateApiKey::class.':show');

                Route::post('{collection_slug}', [StudioApiController::class, 'store'])
                    ->where('collection_slug', $slugPattern)
                    ->middleware(ValidateApiKey::class.':store');

                Route::put('{collection_slug}/{uuid}', [StudioApiController::class, 'update'])
                    ->where('collection_slug', $slugPattern)
                    ->middleware(ValidateApiKey::class.':update');

                Route::delete('{collection_slug}/{uuid}', [StudioApiController::class, 'destroy'])
                    ->where('collection_slug', $slugPattern)
                    ->middleware(ValidateApiKey::class.':destroy');
            });
    }
}
