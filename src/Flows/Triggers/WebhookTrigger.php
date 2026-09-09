<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Triggers;

use Flexpik\FilamentStudio\Flows\Enums\WebhookAuthMode;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowVersion;
use Illuminate\Support\Str;

class WebhookTrigger implements FlowTrigger
{
    public function register(StudioFlowVersion $version): void
    {
        $flow = $version->flow;

        // The flow's webhook_auth_mode column is the only source of truth — it is
        // what FlowWebhookController enforces — so it alone decides whether a
        // secret is needed. See WebhookTriggerConfig.
        if ($flow->webhook_auth_mode === WebhookAuthMode::Hmac && $flow->webhook_secret === null) {
            $flow->forceFill(['webhook_secret' => Str::random(48)])->save();
        }
    }

    public function unregister(StudioFlowVersion $version): void
    {
        $version->flow->forceFill(['webhook_secret' => null])->save();
    }
}
