<?php

namespace App\Support\Woo;

class PayloadHasher
{
    /**
     * Create deterministic hash of a payload array.
     *
     * @param array<string,mixed> $payload
     */
    public static function make(array $payload): string
    {
        $normalized = json_encode(self::ksortRecursive($payload), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return hash('sha256', $normalized ?: '');
    }

    private static function ksortRecursive(array $arr): array
    {
        ksort($arr);
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = self::ksortRecursive($v);
            }
        }
        return $arr;
    }
}
