<?php

declare(strict_types=1);

namespace Directo\Transport;

use Directo\Config;
use Directo\Exception\HttpException;
use Directo\Exception\TransportException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP transport layer wrapping Guzzle.
 *
 * Responsibilities:
 * - Send POST requests with form-encoded parameters
 * - Handle timeouts configured in Config
 * - Convert Guzzle exceptions into SDK exceptions
 * - Never expose auth credentials in exceptions or logs
 * - Log requests/responses when logger provided
 *
 * Design notes:
 * - Implements TransportInterface for testability
 * - Accepts ClientInterface for testing (inject MockHandler)
 * - Accepts PSR-3 LoggerInterface for debugging
 * - All context passed to exceptions excludes auth key
 */
final class Transport implements TransportInterface
{
    /** @var ClientInterface HTTP client for making requests */
    private ClientInterface $httpClient;

    /** @var LoggerInterface PSR-3 logger for debugging */
    private LoggerInterface $logger;

    /**
     * Create a new HTTP transport.
     *
     * @param  Config  $config  SDK configuration
     * @param  ClientInterface|null  $httpClient  Optional Guzzle client (for testing)
     * @param  LoggerInterface|null  $logger  Optional PSR-3 logger (for debugging)
     */
    public function __construct(
        private readonly Config $config,
        ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => $config->timeout,
            'connect_timeout' => $config->connectTimeout,
            'http_errors' => false, // We handle HTTP errors ourselves
        ]);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritDoc}
     */
    public function post(array $formParams, array $context = []): string
    {
        // Add authentication parameter
        $formParams[$this->config->tokenParamName] = $this->config->token;

        // Log request (token redacted)
        $this->logRequest($formParams, $context);

        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->config->baseUrl, [
                'form_params' => $formParams,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/xml, text/xml',
                ],
            ]);

            $body = $this->handleResponse($response, $context);
            $this->logResponse($response, $body, $startTime, $context);

            return $body;
        } catch (ConnectException $e) {
            $this->logError('Connection failed', $e, $startTime, $context);

            throw new TransportException(
                sprintf('Connection failed: %s', $e->getMessage()),
                $context,
                0,
                $e,
            );
        } catch (RequestException $e) {
            // This catches other Guzzle request errors
            if ($e->hasResponse()) {
                $body = $this->handleResponse($e->getResponse(), $context);
                $this->logResponse($e->getResponse(), $body, $startTime, $context);

                return $body;
            }

            $this->logError('Request failed', $e, $startTime, $context);

            throw new TransportException(
                sprintf('Request failed: %s', $e->getMessage()),
                $context,
                0,
                $e,
            );
        } catch (GuzzleException $e) {
            $this->logError('HTTP client error', $e, $startTime, $context);

            throw new TransportException(
                sprintf('HTTP client error: %s', $e->getMessage()),
                $context,
                0,
                $e instanceof \Throwable ? $e : null,
            );
        }
    }

    /**
     * Handle HTTP response and check for errors.
     *
     * @throws HttpException On non-2xx status codes
     */
    private function handleResponse(ResponseInterface $response, array $context): string
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new HttpException(
                sprintf('HTTP request failed with status %d', $statusCode),
                $statusCode,
                $body,
                $context,
            );
        }

        return $body;
    }

    /**
     * Log outgoing request (token redacted).
     *
     * @param  array<string, string|int>  $formParams
     * @param  array<string, mixed>  $context
     */
    private function logRequest(array $formParams, array $context): void
    {
        // Redact token from logs
        $safeParams = $formParams;
        if (isset($safeParams[$this->config->tokenParamName])) {
            $safeParams[$this->config->tokenParamName] = '[REDACTED]';
        }

        $this->logger->debug('Directo API request', [
            'url' => $this->config->baseUrl,
            'params' => $safeParams,
            'context' => $context,
        ]);
    }

    /**
     * Log successful response.
     *
     * @param  string  $body  Response body (truncated in logs)
     * @param  float  $startTime  Request start time
     * @param  array<string, mixed>  $context
     */
    private function logResponse(ResponseInterface $response, string $body, float $startTime, array $context): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $statusCode = $response->getStatusCode();

        $this->logger->debug('Directo API response', [
            'status' => $statusCode,
            'duration_ms' => $duration,
            'body_length' => strlen($body),
            'body_preview' => mb_substr($body, 0, 500).(strlen($body) > 500 ? '...' : ''),
            'context' => $context,
        ]);

        // Log warning for slow requests
        if ($duration > 5000) {
            $this->logger->warning('Slow Directo API request', [
                'duration_ms' => $duration,
                'context' => $context,
            ]);
        }
    }

    /**
     * Log error.
     *
     * @param  string  $message  Error message
     * @param  \Throwable  $exception  The exception
     * @param  float  $startTime  Request start time
     * @param  array<string, mixed>  $context
     */
    private function logError(string $message, \Throwable $exception, float $startTime, array $context): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->logger->error('Directo API error: '.$message, [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'duration_ms' => $duration,
            'context' => $context,
        ]);
    }
}
