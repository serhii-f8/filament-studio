<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Triggers;

use Flexpik\FilamentStudio\Contracts\Flows\FlowTriggerConfig;

/**
 * The webhook trigger node carries no configuration.
 *
 * Everything about an inbound webhook is a property of the flow record, not of
 * the node: auth mode, the generated secret, the allowed API keys and the redact
 * paths all live on studio_flows and are edited in FlowResource's "Webhook
 * Security" section. Declaring node-level copies of them gave the designer a
 * second, losing source of truth — a node that said auth_mode=none while the
 * flow still enforced HMAC, and a secret_key field nothing ever read.
 */
class WebhookTriggerConfig implements FlowTriggerConfig
{
    /** @return array<string, mixed> */
    public function schema(): array
    {
        return ['fields' => []];
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [];
    }

    /** @param array<string, mixed> $config */
    public function validate(array $config): void
    {
        // Nothing to validate: the node holds no configuration.
    }
}
