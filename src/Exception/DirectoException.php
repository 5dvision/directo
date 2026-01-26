<?php

declare(strict_types=1);

namespace Directo\Exception;

use Exception;
use Throwable;

/**
 * Base exception for all Directo SDK errors.
 *
 * All SDK exceptions extend this class, allowing consumers to catch
 * a single type for all Directo-related errors.
 *
 * Context is stored separately to provide debugging info without
 * leaking sensitive data (auth keys are explicitly excluded).
 */
class DirectoException extends Exception
{
    /**
     * Create a new Directo exception.
     *
     * @param  string  $message  Error message
     * @param  array<string, mixed>  $context  Debugging context (no auth data)
     * @param  int  $code  Error code
     * @param  Throwable|null  $previous  Previous exception for chaining
     */
    public function __construct(
        string $message,
        protected readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get debugging context.
     *
     * Context includes endpoint info and filters but NEVER auth credentials.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
