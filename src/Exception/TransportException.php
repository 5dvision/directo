<?php

declare(strict_types=1);

namespace Directo\Exception;

/**
 * Exception for network/transport layer errors.
 *
 * Wraps Guzzle connection errors, timeouts, DNS failures, etc.
 * The original Guzzle exception is available via getPrevious().
 */
class TransportException extends DirectoException
{
}
