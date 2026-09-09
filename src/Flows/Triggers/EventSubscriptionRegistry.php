<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Triggers;

use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Illuminate\Support\Facades\Cache;

/**
 * Which flows want to hear about which collection events.
 *
 * The cache is a read-through index, never the source of truth: that is the set
 * of published versions whose trigger node is a collection_event. A cache flush
 * — a deploy running `optimize:clear`, an eviction, a restarted cache server —
 * used to silently unsubscribe every flow, with nothing logged and nothing shown
 * in the UI. A missing entry now rebuilds from the database instead.
 */
class EventSubscriptionRegistry
{
    private const CACHE_KEY = 'studio.flows.collection_event_subscriptions';

    /** @param array<int, string> $events */
    public function subscribe(string $flowId, string $collectionSlug, array $events, string $versionId): void
    {
        $all = $this->all();
        $all[$flowId] = compact('collectionSlug', 'events', 'versionId');
        Cache::forever(self::CACHE_KEY, $all);
    }

    public function unsubscribe(string $flowId): void
    {
        $all = $this->all();
        unset($all[$flowId]);
        Cache::forever(self::CACHE_KEY, $all);
    }

    /** @return array<int, string> */
    public function matching(string $collectionSlug, string $event): array
    {
        return collect($this->all())
            ->filter(fn ($entry) => $entry['collectionSlug'] === $collectionSlug && in_array($event, $entry['events'], true))
            ->keys()
            ->all();
    }

    /** @return array<string, array{collectionSlug: string, events: array<int, string>, versionId: string}> */
    public function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        // An explicitly empty map is a real answer — every flow unsubscribed.
        // Only a missing key means the index was lost and must be rebuilt.
        if ($cached !== null) {
            return $cached;
        }

        $rebuilt = $this->rebuildFromPublishedVersions();
        Cache::forever(self::CACHE_KEY, $rebuilt);

        return $rebuilt;
    }

    /**
     * Derive the index from what is actually published.
     *
     * @return array<string, array{collectionSlug: string, events: array<int, string>, versionId: string}>
     */
    private function rebuildFromPublishedVersions(): array
    {
        $subscriptions = [];

        StudioFlow::query()
            ->whereNotNull('published_version_id')
            ->with('publishedVersion')
            ->cursor()
            ->each(function (StudioFlow $flow) use (&$subscriptions) {
                $version = $flow->publishedVersion;
                if ($version === null) {
                    return;
                }

                $node = collect($version->graph['nodes'] ?? [])->firstWhere('type', 'trigger');
                if (($node['data']['triggerType'] ?? null) !== 'collection_event') {
                    return;
                }

                $config = $node['data']['config'] ?? [];
                $collectionSlug = (string) ($config['collection'] ?? '');
                $events = array_values((array) ($config['events'] ?? []));

                if ($collectionSlug === '' || $events === []) {
                    return;
                }

                $subscriptions[$flow->id] = [
                    'collectionSlug' => $collectionSlug,
                    'events' => $events,
                    'versionId' => (string) $version->id,
                ];
            });

        return $subscriptions;
    }
}
