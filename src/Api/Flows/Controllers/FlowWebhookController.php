<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Api\Flows\Controllers;

use Flexpik\FilamentStudio\Flows\Engine\FlowDispatcher;
use Flexpik\FilamentStudio\Flows\Enums\FlowStatus;
use Flexpik\FilamentStudio\Flows\Enums\WebhookAuthMode;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Security\HmacWebhookVerifier;
use Flexpik\FilamentStudio\Flows\Security\MasksSensitiveValues;
use Flexpik\FilamentStudio\Models\StudioApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FlowWebhookController
{
    public function __construct(
        private FlowDispatcher $dispatcher,
        private HmacWebhookVerifier $hmac,
        private MasksSensitiveValues $masker,
    ) {}

    public function handle(Request $request, string $flowSlug): JsonResponse
    {
        $flow = StudioFlow::query()->where('slug', $flowSlug)->first();
        if ($flow === null) {
            abort(404);
        }
        if ($flow->status !== FlowStatus::Active) {
            abort(409, 'Flow is not active');
        }

        $rawBody = $request->getContent();
        $authMode = $flow->webhook_auth_mode;

        if ($authMode === WebhookAuthMode::Hmac) {
            $sig = (string) $request->header('X-Studio-Signature', '');
            $timestamp = (string) $request->header('X-Studio-Timestamp', '');
            try {
                $this->hmac->verify($rawBody, $sig, $timestamp, (string) $flow->webhook_secret);
            } catch (\Throwable) {
                abort(401);
            }
        } elseif ($authMode === WebhookAuthMode::ApiKey) {
            $plainKey = (string) $request->header('X-Api-Key', '');
            if ($plainKey === '') {
                abort(401);
            }
            $apiKey = StudioApiKey::findByKey($plainKey);
            if ($apiKey === null) {
                abort(401);
            }
            $allowList = $flow->webhook_allowed_studio_api_key_ids ?? [];
            if (count($allowList) > 0 && ! in_array((string) $apiKey->id, array_map('strval', $allowList), true)) {
                abort(403);
            }
        }
        // WebhookAuthMode::None — no auth check

        $payload = [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'query' => $request->query->all(),
            'body' => $this->decodeBody($request, $rawBody),
            'raw' => $rawBody,
        ];

        $payload = $this->applyRedactPaths($payload, $flow->webhook_redact_paths ?? []);
        $payload = $this->masker->mask($payload);
        $payload['raw'] = $this->sanitizedRaw($payload['body'], $rawBody);

        try {
            $run = $this->dispatcher->dispatchAsync(
                flow: $flow,
                triggerType: 'webhook',
                payload: $payload,
                accountability: ['user_id' => null, 'tenant_id' => $flow->tenant_id, 'role' => null, 'source' => 'webhook'],
            );
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'no_published_version') {
                return response()->json(['error' => 'no_published_version'], 409);
            }
            throw $e;
        }

        return response()->json(['data' => ['flow_run_id' => $run->id]], 202);
    }

    /**
     * Decode the request body into something templates can address.
     *
     * JSON and form-encoded senders both get an array; anything else stays a raw
     * string, which is still reachable through the payload's `raw` key.
     */
    private function decodeBody(Request $request, string $rawBody): array|string
    {
        if ($request->isJson()) {
            return $request->json()->all();
        }

        if (str_contains((string) $request->header('Content-Type', ''), 'application/x-www-form-urlencoded')) {
            parse_str($rawBody, $parsed);

            if ($parsed !== []) {
                return $parsed;
            }
        }

        $formParams = $request->request->all();

        return $formParams !== [] ? $formParams : $rawBody;
    }

    /**
     * Keep `raw` consistent with the redacted and masked body.
     *
     * Redact paths and key-pattern masking only reach the parsed body, so a value
     * scrubbed from `body` used to survive verbatim in `raw` — and both are
     * persisted on the run. Re-encoding from the sanitized body closes that. A
     * body we could not parse has no paths to redact, so it passes through: do
     * not send secrets in a body shape the webhook cannot decode.
     */
    private function sanitizedRaw(array|string $sanitizedBody, string $rawBody): string
    {
        if (is_string($sanitizedBody)) {
            return $rawBody;
        }

        return json_encode($sanitizedBody, JSON_UNESCAPED_SLASHES) ?: $rawBody;
    }

    /** @param  array<int, string>  $paths */
    private function applyRedactPaths(array $payload, array $paths): array
    {
        foreach ($paths as $path) {
            $parts = array_filter(explode('/', ltrim((string) $path, '/')));
            $payload = $this->redactAtPath($payload, array_values($parts));
        }

        return $payload;
    }

    /** @param  array<int, string>  $parts */
    private function redactAtPath(array $data, array $parts): array
    {
        if (count($parts) === 0) {
            return $data;
        }
        $key = $parts[0];
        if (! array_key_exists($key, $data)) {
            return $data;
        }
        if (count($parts) === 1) {
            $data[$key] = '***';

            return $data;
        }
        if (is_array($data[$key])) {
            $data[$key] = $this->redactAtPath($data[$key], array_slice($parts, 1));
        }

        return $data;
    }
}
