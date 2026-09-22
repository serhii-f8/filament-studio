<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Mcp\ConfirmTokens\ConfirmTokenIssuer;
use Flexpik\FilamentStudio\Mcp\ConfirmTokens\ConfirmTokenStore;
use Flexpik\FilamentStudio\Mcp\Exceptions\ConfirmTokenInvalidException;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    config(['filament-studio.mcp.confirm_token_ttl' => 300]);
    $this->store = new ConfirmTokenStore;
    $this->issuer = new ConfirmTokenIssuer($this->store);
});

it('issues a token with a ct_ prefix', function () {
    $result = $this->issuer->issue(operation: 'delete_collection', target: ['slug' => 'products'], tenantId: 1);

    expect($result['token'])->toStartWith('ct_')
        ->and($result['expires_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

it('consumes a valid token exactly once', function () {
    $token = $this->issuer->issue('delete_collection', ['slug' => 'products'], 1)['token'];

    $payload = $this->store->consume($token, 'delete_collection', ['slug' => 'products'], 1);

    expect($payload['operation'])->toBe('delete_collection');
    expect(fn () => $this->store->consume($token, 'delete_collection', ['slug' => 'products'], 1))
        ->toThrow(ConfirmTokenInvalidException::class);
});

it('rejects a token whose payload does not match', function () {
    $token = $this->issuer->issue('delete_collection', ['slug' => 'products'], 1)['token'];

    expect(fn () => $this->store->consume($token, 'delete_collection', ['slug' => 'orders'], 1))
        ->toThrow(ConfirmTokenInvalidException::class);
});

it('rejects a token used by a different tenant', function () {
    $token = $this->issuer->issue('delete_collection', ['slug' => 'products'], 1)['token'];

    expect(fn () => $this->store->consume($token, 'delete_collection', ['slug' => 'products'], 2))
        ->toThrow(ConfirmTokenInvalidException::class);
});

it('rejects an expired token', function () {
    config(['filament-studio.mcp.confirm_token_ttl' => 1]);
    $token = (new ConfirmTokenIssuer(new ConfirmTokenStore))
        ->issue('delete_collection', ['slug' => 'products'], 1)['token'];

    sleep(2);

    expect(fn () => $this->store->consume($token, 'delete_collection', ['slug' => 'products'], 1))
        ->toThrow(ConfirmTokenInvalidException::class);
});

it('reports a reused token as consumed, not expired', function () {
    $token = $this->issuer->issue('delete_collection', ['slug' => 'products'], 1)['token'];
    $this->store->consume($token, 'delete_collection', ['slug' => 'products'], 1);

    try {
        $this->store->consume($token, 'delete_collection', ['slug' => 'products'], 1);
        $this->fail('Expected ConfirmTokenInvalidException');
    } catch (ConfirmTokenInvalidException $e) {
        expect($e->mcpCode())->toBe('CONSUMED_CONFIRM_TOKEN');
    }
});

it('reports a consumed token as consumed even when replayed against another target', function () {
    $token = $this->issuer->issue('delete_collection', ['slug' => 'products'], 1)['token'];
    $this->store->consume($token, 'delete_collection', ['slug' => 'products'], 1);

    try {
        $this->store->consume($token, 'delete_collection', ['slug' => 'orders'], 1);
        $this->fail('Expected ConfirmTokenInvalidException');
    } catch (ConfirmTokenInvalidException $e) {
        expect($e->mcpCode())->toBe('CONSUMED_CONFIRM_TOKEN');
    }
});

it('does not reveal a consumed token to another tenant', function () {
    $token = $this->issuer->issue('delete_collection', ['slug' => 'products'], 1)['token'];
    $this->store->consume($token, 'delete_collection', ['slug' => 'products'], 1);

    try {
        $this->store->consume($token, 'delete_collection', ['slug' => 'products'], 2);
        $this->fail('Expected ConfirmTokenInvalidException');
    } catch (ConfirmTokenInvalidException $e) {
        expect($e->mcpCode())->toBe('EXPIRED_CONFIRM_TOKEN');
    }
});

it('still reports a never-issued token as expired', function () {
    try {
        $this->store->consume('ct_doesnotexist', 'delete_collection', ['slug' => 'products'], 1);
        $this->fail('Expected ConfirmTokenInvalidException');
    } catch (ConfirmTokenInvalidException $e) {
        expect($e->mcpCode())->toBe('EXPIRED_CONFIRM_TOKEN');
    }
});
