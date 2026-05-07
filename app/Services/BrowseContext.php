<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class BrowseContext
{
    private const TTL_MINUTES = 30;

    /**
     * @param  string[]  $recipeIds
     */
    public function store(array $recipeIds, int $telegramId): string
    {
        $key = (string) $telegramId;
        Cache::put("browse:{$key}", $recipeIds, now()->addMinutes(self::TTL_MINUTES));

        return $key;
    }

    /**
     * @return string[]|null
     */
    public function get(string $key): ?array
    {
        return Cache::get("browse:{$key}");
    }
}
