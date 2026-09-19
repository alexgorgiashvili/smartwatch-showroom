<?php

namespace App\Services;

use Illuminate\Support\Arr;

class ContentStudioRevisionDiff
{
    /** A safe field-level diff for review UI; content remains in immutable revision JSON. */
    public function between(array $previous, array $current): array
    {
        $before = Arr::dot($previous); $after = Arr::dot($current); $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        $changes = [];
        foreach ($keys as $key) if (($before[$key] ?? null) !== ($after[$key] ?? null)) $changes[$key] = ['from' => $before[$key] ?? null, 'to' => $after[$key] ?? null];
        return $changes;
    }
}
