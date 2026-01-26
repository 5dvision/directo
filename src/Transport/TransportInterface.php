<?php

declare(strict_types=1);

namespace Directo\Transport;

/**
 * Interface for HTTP transport layer.
 *
 * Enables dependency injection and testing without Guzzle MockHandler.
 * Implement this interface to create custom transport implementations
 * (e.g., for caching, custom retry logic, or testing).
 */
interface TransportInterface
{
    /**
     * Send a POST request with form parameters.
     *
     * The implementation is responsible for:
     * - Adding authentication parameters
     * - Handling timeouts
     * - Converting transport errors into SDK exceptions
     *
     * @param  array<string, string|int>  $formParams  Form parameters
     * @param  array<string, mixed>  $context  Context for error reporting (must not contain auth credentials)
     * @return string Response body
     *
     * @throws \Directo\Exception\TransportException On network/connection errors
     * @throws \Directo\Exception\HttpException On non-2xx responses
     */
    public function post(array $formParams, array $context = []): string;
}
