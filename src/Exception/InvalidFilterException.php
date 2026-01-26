<?php

declare(strict_types=1);

namespace Directo\Exception;

/**
 * Exception for invalid filter parameters.
 *
 * Thrown when:
 * - An unknown filter key is passed (developer error - fail fast)
 * - A filter value is not scalar or stringable
 *
 * This is a developer error, not a runtime error. The SDK fails fast
 * to prevent silent bugs from reaching production.
 */
class InvalidFilterException extends DirectoException
{
    /**
     * Create exception for unknown filter keys.
     *
     * @param  array<int, string>  $unknownKeys  Filter keys that are not allowed
     * @param  array<int, string>  $allowedKeys  List of allowed filter keys
     * @param  string  $endpoint  The endpoint name
     */
    public static function unknownFilters(array $unknownKeys, array $allowedKeys, string $endpoint): self
    {
        $unknown = implode(', ', $unknownKeys);
        $allowed = implode(', ', $allowedKeys);

        return new self(
            sprintf(
                'Unknown filter(s) [%s] for endpoint "%s". Allowed filters: [%s]',
                $unknown,
                $endpoint,
                $allowed,
            ),
            [
                'endpoint' => $endpoint,
                'unknown_filters' => $unknownKeys,
                'allowed_filters' => $allowedKeys,
            ],
        );
    }

    /**
     * Create exception for invalid filter value type.
     *
     * @param  string  $key  Filter key
     * @param  mixed  $value  The invalid value
     * @param  string  $endpoint  The endpoint name
     */
    public static function invalidValueType(string $key, mixed $value, string $endpoint): self
    {
        $type = get_debug_type($value);

        return new self(
            sprintf(
                'Filter "%s" for endpoint "%s" must be scalar or Stringable, got "%s"',
                $key,
                $endpoint,
                $type,
            ),
            [
                'endpoint' => $endpoint,
                'filter_key' => $key,
                'value_type' => $type,
            ],
        );
    }
}
