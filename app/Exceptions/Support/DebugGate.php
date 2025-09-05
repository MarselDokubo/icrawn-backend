<?php

namespace App\Support;

use Illuminate\Http\Request;

class DebugGate
{
    public static function enabled(Request $request): bool
    {
        if (filter_var(env('DEBUG_ERRORS', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        $hdr = $request->headers->get('X-Debug');
        return $hdr === '1' || $hdr === 'true';
    }

    /** @param array<int,mixed> $bindings */
    public static function sanitizeBindings(array $bindings): array
    {
        return array_map(function ($v) {
            if (is_string($v) && strlen($v) > 256) return substr($v, 0, 256) . '…';
            return $v;
        }, $bindings);
    }
}
