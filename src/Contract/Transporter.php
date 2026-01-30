<?php

declare(strict_types=1);

namespace Directo\Contract;

use Directo\Exception\TransportException;
use Directo\Exception\HttpException;

/**
 * Interface for HTTP transport layer.
 *
 * Enables dependency injection and testing.
 */
interface Transporter
{
    /**
     * Send a POST request with form parameters.
     *
     * @param  array<string, string|int>  $formParams  Form parameters
     * @param  array<string, mixed>  $context  Context for error reporting
     * @return string Response body
     *
     * @throws TransportException On network/connection errors
     * @throws HttpException On non-2xx responses
     */
    public function post(array $formParams, array $context = []): string;
}
