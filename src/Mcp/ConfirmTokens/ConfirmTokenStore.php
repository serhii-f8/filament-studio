<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Mcp\ConfirmTokens;

use Flexpik\FilamentStudio\Mcp\Exceptions\ConfirmTokenInvalidException;
use Illuminate\Support\Facades\Cache;

class ConfirmTokenStore
{
    /**
     * @param  array<string, mixed>  $target
     */
    public function put(string $token, string $operation, array $target, ?int $tenantId, int $ttlSeconds): void
    {
        Cache::put(
            $this->key($tenantId, $token),
            [
                'operation' => $operation,
                'target' => $target,
                'tenant_id' => $tenantId,
                'issued_at' => now()->toIso8601String(),
            ],
            $ttlSeconds,
        );
    }

    /**
     * @param  array<string, mixed>  $expectedTarget
     * @return array<string, mixed>
     *
     * @throws ConfirmTokenInvalidException
     */
    public function consume(string $token, string $expectedOperation, array $expectedTarget, ?int $tenantId): array
    {
        $key = $this->key($tenantId, $token);
        $consumedKey = $key.':consumed';

        if (Cache::has($consumedKey)) {
            throw ConfirmTokenInvalidException::consumed($token);
        }

        $payload = Cache::get($key);

        if ($payload === null) {
            throw ConfirmTokenInvalidException::expired($token);
        }

        if ($payload['operation'] !== $expectedOperation || $payload['target'] !== $expectedTarget || $payload['tenant_id'] !== $tenantId) {
            throw ConfirmTokenInvalidException::mismatched($token);
        }

        // Leave a marker behind so a replay learns the operation already ran, rather
        // than being told the token expired. `add` is atomic, so of two concurrent
        // calls with the same token only one gets past this line.
        $ttl = (int) config('filament-studio.mcp.confirm_token_ttl', 300);
        if (! Cache::add($consumedKey, true, $ttl)) {
            throw ConfirmTokenInvalidException::consumed($token);
        }

        Cache::forget($key);

        return $payload;
    }

    private function key(?int $tenantId, string $token): string
    {
        return sprintf('studio:mcp:confirm:%d:%s', $tenantId, $token);
    }
}
