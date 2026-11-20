<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Support;

use Composer\InstalledVersions;
use Throwable;

final class PackageVersion
{
    public const PACKAGE = 'serhii-f8/filament-studio';

    public const FALLBACK = '0.0.0-dev';

    /**
     * Resolve the installed version of the package from Composer's runtime metadata.
     */
    public static function get(string $package = self::PACKAGE): string
    {
        try {
            if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)) {
                return InstalledVersions::getPrettyVersion($package) ?? self::FALLBACK;
            }
        } catch (Throwable) {
            // Composer metadata unavailable — fall through to the fallback.
        }

        return self::FALLBACK;
    }
}
