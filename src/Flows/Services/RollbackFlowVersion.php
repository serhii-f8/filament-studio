<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Services;

use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowVersion;
use RuntimeException;

class RollbackFlowVersion
{
    public function __construct(private PublishFlowVersion $publisher) {}

    public function rollback(StudioFlow $flow, StudioFlowVersion $target, ?string $publishedBy = null): StudioFlowVersion
    {
        if ($target->flow_id !== $flow->id) {
            throw new RuntimeException('version_belongs_to_other_flow');
        }

        $flow->auditEvent = 'rolled_back';

        $flow->forceFill([
            'draft_graph' => $target->graph,
            'draft_updated_at' => now(),
        ])->save();

        // fresh() drops the in-memory hint, so re-apply it for the publish half.
        $restored = $flow->fresh();
        $restored->auditEvent = 'rolled_back';

        return $this->publisher->publish(
            $restored,
            changeSummary: "Restored from v{$target->version}",
            publishedBy: $publishedBy,
        );
    }
}
