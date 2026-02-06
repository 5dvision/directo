<?php

declare(strict_types=1);

namespace Directo\Endpoint;

use Directo\Config;
use Directo\Contract\Transporter;
use Directo\Contract\Endpoint;
use Directo\Exception\InvalidFilterException;
use Directo\Http\ErrorResponseDetector;
use Directo\Http\RequestBuilder;
use Directo\Http\ResponseParser;
use Directo\Schema\SchemaRegistry;
use Stringable;

/**
 * Base class for all Directo XMLCore endpoints.
 *
 * Provides common functionality:
 * - Filter validation
 * - Request building (GET with filters, PUT with XML body)
 * - Error response detection
 * - Response parsing
 * - Optional schema validation (using endpoint-defined schemas)
 *
 * To create a new endpoint:
 * 1. Extend this class
 * 2. Implement what(), allowedFilters(), xmlElements(), schemas()
 * 3. That's it! No registry updates needed.
 *
 * Design notes:
 * - Template method pattern: subclasses define endpoint-specific config
 * - All HTTP/parsing logic is reused
 * - Testable: Transport can be mocked
 * - Schemas defined per endpoint, not in external registry
 */
abstract class AbstractEndpoint implements Endpoint
{
    /**
     * Create endpoint with all dependencies.
     *
     * @param  Config  $config  SDK configuration
     * @param  Transporter  $transport  HTTP transport layer
     * @param  SchemaRegistry  $schemaRegistry  Schema validator
     * @param  ResponseParser  $parser  XML response parser
     * @param  ErrorResponseDetector  $errorDetector  Error response detector
     * @param  RequestBuilder  $xmlBuilder  XML request builder
     */
    public function __construct(
        protected readonly Config $config,
        protected readonly Transporter $transport,
        protected readonly SchemaRegistry $schemaRegistry,
        protected readonly ResponseParser $parser,
        protected readonly ErrorResponseDetector $errorDetector,
        protected readonly RequestBuilder $xmlBuilder,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, scalar|Stringable> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $this->validateFilters($filters);

        $formParams = $this->buildFormParams($filters);
        $context = $this->buildContext($filters);

        $responseXml = $this->transport->post($formParams, $context);

        // Check for Directo-specific error responses (even with HTTP 200)
        $this->errorDetector->detectAndThrow($responseXml, $context);

        // Optional schema validation (using endpoint-defined schema)
        $schema = $this->schemas()['list'] ?? null;
        if ($this->config->validateSchema && is_string($schema)) {
            $this->schemaRegistry->validateXml($responseXml, $schema, $context);
        }

        return $this->parser->parse($responseXml, $context);
    }

    /**
     * Validates filters against allowed list.
     *
     * @param  array<string, mixed>  $filters
     * @throws InvalidFilterException
     */
    protected function validateFilters(array $filters): void
    {
        $allowed = $this->allowedFilters();
        $provided = array_keys($filters);
        $unknown = array_diff($provided, $allowed);

        if ($unknown !== []) {
            throw InvalidFilterException::unknownFilters($unknown, $allowed, $this->what());
        }

        // Validate value types
        foreach ($filters as $key => $value) {
            if (! $this->isValidFilterValue($value)) {
                throw InvalidFilterException::invalidValueType($key, $value, $this->what());
            }
        }
    }

    /**
     * Checks if filter value is valid (scalar or Stringable).
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function isValidFilterValue(mixed $value): bool
    {
        return is_scalar($value) || $value instanceof Stringable;
    }

    /**
     * Builds form parameters for the request.
     *
     * @param  array<string, scalar|Stringable>  $filters
     * @return array<string, string|int>
     */
    protected function buildFormParams(array $filters): array
    {
        $params = [
            'get' => 1,
            'what' => $this->what(),
        ];

        // Add filters (cast to string for consistency)
        foreach ($filters as $key => $value) {
            $params[$key] = (string) $value;
        }

        return $params;
    }

    /**
     * Builds context for error reporting.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildContext(array $filters): array
    {
        return [
            'endpoint' => static::class,
            'what' => $this->what(),
            'filters' => $filters,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function put(array $data): array
    {
        /** @var array{root: string, record: string, key?: string} $elements */
        $elements = $this->xmlElements();
        $xmlData = $this->xmlBuilder->build(
            $elements['root'],
            $elements['record'],
            $data,
            $elements['key'] ?? null,
        );

        return $this->sendPutRequest($xmlData, ['data' => $data]);
    }

    /**
     * {@inheritDoc}
     */
    public function putBatch(array $records): array
    {
        /** @var array{root: string, record: string, key?: string} $elements */
        $elements = $this->xmlElements();
        $xmlData = $this->xmlBuilder->buildBatch(
            $elements['root'],
            $elements['record'],
            $records,
            $elements['key'] ?? null,
        );

        return $this->sendPutRequest($xmlData, ['records' => count($records)]);
    }

    /**
     * Sends a PUT request with XML data.
     *
     * @param  string  $xmlData  The XML body
     * @param  array<string, mixed>  $extraContext  Additional context for errors
     * @return array<int, array<string, mixed>>  Parsed response
     */
    protected function sendPutRequest(string $xmlData, array $extraContext = []): array
    {
        $context = array_merge([
            'endpoint' => static::class,
            'what' => $this->what(),
            'operation' => 'put',
        ], $extraContext);

        // Validate request XML against input schema if enabled (using endpoint-defined schema)
        $schema = $this->schemas()['put'] ?? null;
        if ($this->config->validateSchema && is_string($schema)) {
            $this->schemaRegistry->validateXml($xmlData, $schema, $context);
        }

        $formParams = [
            'put' => 1,
            'what' => $this->what(),
            'xmldata' => $xmlData,
        ];

        $responseXml = $this->transport->post($formParams, $context);

        // Check for Directo-specific error responses
        $this->errorDetector->detectAndThrow($responseXml, $context);

        return $this->parser->parse($responseXml, $context);
    }

    /**
     * Sends a PUT request with raw XML data.
     *
     * Useful when you have pre-built XML or want to bypass the array-to-XML builder.
     *
     * @param  string  $xmlData  The complete XML body
     * @return array<string, mixed>
     */
    public function putRaw(string $xmlData): array
    {
        return $this->sendPutRequest($xmlData);
    }
}
