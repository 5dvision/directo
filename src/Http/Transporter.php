<?php

declare(strict_types=1);

namespace Directo\Http;

use Directo\Config;
use Directo\Contract\Transporter as TransporterContract;
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
 */
final readonly class Transporter implements TransporterContract
{
    /** @var ClientInterface HTTP client for making requests */
    private ClientInterface $httpClient;

    public function __construct(
        private Config $config,
        ?ClientInterface $httpClient = null,
        private ?LoggerInterface $logger = new NullLogger(),
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => $config->timeout,
            'connect_timeout' => $config->connectTimeout,
            'http_errors' => false,
        ]);
    }

    /**
     * @param array<string, string|int> $formParams
     * @param array<string, mixed> $context
     */
    public function post(array $formParams, array $context = []): string
    {
        $formParams[$this->config->tokenParamName] = $this->config->token;
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
            throw new TransportException(sprintf('Connection failed: %s', $e->getMessage()), $context, 0, $e);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $body = $this->handleResponse($e->getResponse(), $context);
                $this->logResponse($e->getResponse(), $body, $startTime, $context);
                return $body;
            }

            $this->logError('Request failed', $e, $startTime, $context);
            throw new TransportException(sprintf('Request failed: %s', $e->getMessage()), $context, 0, $e);
        } catch (GuzzleException $e) {
            $this->logError('HTTP client error', $e, $startTime, $context);
            throw new TransportException(sprintf('HTTP client error: %s', $e->getMessage()), $context, 0, $e instanceof \Throwable ? $e : null);
        }
    }

    private function handleResponse(ResponseInterface $response, array $context): string
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new HttpException(sprintf('HTTP request failed with status %d', $statusCode), $statusCode, $body, $context);
        }

        return $body;
    }

    private function logRequest(array $formParams, array $context): void
    {
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

    private function logResponse(ResponseInterface $response, string $body, float $startTime, array $context): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->logger->debug('Directo API response', [
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'body_length' => strlen($body),
            'body_preview' => mb_substr($body, 0, 500).(strlen($body) > 500 ? '...' : ''),
            'context' => $context,
        ]);

        if ($duration > 5000) {
            $this->logger->warning('Slow Directo API request', [
                'duration_ms' => $duration,
                'context' => $context,
            ]);
        }
    }

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
