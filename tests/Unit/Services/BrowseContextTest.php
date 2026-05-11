<?php

use App\Services\BrowseContext;
use Illuminate\Support\Facades\Cache;

it('stores recipe ids under telegram_id key', function () {
    $key = new BrowseContext()->store(['uuid-1', 'uuid-2', 'uuid-3'], 123456789);

    expect($key)->toBe('123456789')
        ->and(Cache::has('browse:123456789'))->toBeTrue();
});

it('retrieves stored ids by telegram_id key', function () {
    $ctx = new BrowseContext();
    $ids = ['uuid-a', 'uuid-b'];

    $key = $ctx->store($ids, 987654321);

    expect($ctx->get($key))->toBe($ids);
});

it('returns null for non-existent key', function () {
    expect(new BrowseContext()->get('nonexist'))->toBeNull();
});

it('overwrites previous context for same user', function () {
    $ctx = new BrowseContext();
    $ctx->store(['old-uuid'], 111);
    $ctx->store(['new-uuid'], 111);

    expect($ctx->get('111'))->toBe(['new-uuid']);
});

it('returns null after cache is cleared', function () {
    $ctx = new BrowseContext();
    $key = $ctx->store(['uuid-1'], 555);
    Cache::forget("browse:{$key}");

    expect($ctx->get($key))->toBeNull();
});
