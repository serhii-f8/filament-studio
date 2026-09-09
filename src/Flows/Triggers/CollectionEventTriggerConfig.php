<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Triggers;

use Flexpik\FilamentStudio\Contracts\Flows\FlowTriggerConfig;

class CollectionEventTriggerConfig implements FlowTriggerConfig
{
    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'fields' => [
                ['name' => 'collection', 'type' => 'text', 'label' => 'Collection Slug'],
                ['name' => 'events', 'type' => 'code', 'label' => 'Events'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return ['events' => ['created']];
    }

    /** @param array<string, mixed> $config */
    public function validate(array $config): void
    {
        // Collection and event validation happens at runtime
    }
}
