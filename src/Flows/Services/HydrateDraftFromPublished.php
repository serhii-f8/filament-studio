<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Services;

use Flexpik\FilamentStudio\Flows\Models\StudioFlow;

class HydrateDraftFromPublished
{
    public function hydrate(StudioFlow $flow): StudioFlow
    {
        if ($flow->draft_graph !== null) {
            return $flow;
        }
        $published = $flow->publishedVersion;
        if ($published === null) {
            return $flow;
        }

        // A copy of the published graph is not an unsaved draft: leave draft_updated_at null
        // (as publishing does) so it is only set once the user actually saves a change.
        $flow->forceFill([
            'draft_graph' => $published->graph,
            'draft_updated_at' => null,
        ])->save();

        return $flow;
    }
}
