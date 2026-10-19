<?php

namespace App\Support;

/**
 * Formats Cloud API values for display. The API doesn't always match its
 * documented types (some "string" fields come back as objects), so anything
 * that isn't a plain scalar is shown as JSON instead of breaking the view.
 */
class Display
{
    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '', $value === [] => '-',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        };
    }

    /**
     * A status for the badge: Cloud's status string, or the nested "status"
     * key when the API returns an object.
     */
    public static function status(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['status'] ?? $value['value'] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : 'unknown';
    }
}
