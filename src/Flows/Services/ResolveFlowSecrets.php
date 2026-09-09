<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Services;

use Flexpik\FilamentStudio\Flows\Models\StudioFlow;

/**
 * Loads a flow's encrypted secrets into the plain key => value bag the engine
 * exposes to templates as `{{ $secrets.KEY }}`.
 *
 * Secrets belong to a flow, so a sub-flow resolves its own — a parent never
 * hands its credentials to a flow it triggers.
 */
class ResolveFlowSecrets
{
    /** @return array<string, string> */
    public function for(StudioFlow $flow): array
    {
        return $flow->secrets()
            ->get()
            ->mapWithKeys(fn ($secret) => [(string) $secret->key => (string) $secret->value])
            ->all();
    }
}
