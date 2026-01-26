<?php

declare(strict_types=1);

namespace Directo;

use Directo\Endpoint\CustomersEndpoint;
use Directo\Endpoint\EndpointInterface;
use Directo\Endpoint\ItemsEndpoint;
use Directo\Parser\ErrorResponseDetector;
use Directo\Parser\XmlRequestBuilder;
use Directo\Parser\XmlResponseParser;
use Directo\Schema\SchemaRegistry;
use Directo\Transport\Transport;
use Directo\Transport\TransportInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Directo XMLCore API client.
 *
 * Main entry point for the SDK. Provides access to all endpoints.
 *
 * @example Basic usage
 * ```php
 * $client = new DirectoClient(new Config(
 *     token: 'your-api-token',
 * ));
 *
 * // List all customers
 * $customers = $client->customers()->list();
 *
 * // List customers with filters
 * $customers = $client->customers()->list([
 *     'code' => 'CUST001',
 *     'closed' => 0,
 * ]);
 *
 * // List items
 * $items = $client->items()->list(['class' => 'ELECTRONICS']);
 * ```
 * @example With schema validation (for debugging)
 * ```php
 * $client = new DirectoClient(new Config(
 *     token: 'your-api-token',
 *     validateSchema: true, // Enable XSD validation
 * ));
 * ```
 * @example With PSR-3 logger
 * ```php
 * $client = new DirectoClient(
 *     new Config(token: 'your-api-token'),
 *     logger: $monolog, // Any PSR-3 logger
 * );
 * ```
 *
 * Design notes:
 * - Lazy endpoint instantiation (created on first access)
 * - Endpoints are cached for reuse
 * - Shared dependencies (parser, schema registry, error detector) created once
 * - Transport can be injected for testing (MockHandler)
 * - Logger can be injected for debugging
 * - Simple factory pattern: no DI container needed
 */
final class DirectoClient
{
    /** @var TransportInterface HTTP transport layer */
    private TransportInterface $transport;

    /** @var SchemaRegistry XSD schema validator */
    private SchemaRegistry $schemaRegistry;

    /** @var XmlResponseParser XML response parser */
    private XmlResponseParser $parser;

    /** @var ErrorResponseDetector API error detector */
    private ErrorResponseDetector $errorDetector;

    /** @var XmlRequestBuilder XML request builder */
    private XmlRequestBuilder $xmlBuilder;

    /** @var CustomersEndpoint|null Cached customers endpoint */
    private ?CustomersEndpoint $customersEndpoint = null;

    /** @var ItemsEndpoint|null Cached items endpoint */
    private ?ItemsEndpoint $itemsEndpoint = null;

    /**
     * Create a new Directo client.
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
        $this->transport = new Transport($config, $httpClient, $logger);
        $this->schemaRegistry = new SchemaRegistry(
            $config->getSchemaBasePath(),
            $config->schemaBaseUrl,
        );
        $this->parser = new XmlResponseParser(
            treatEmptyAsNull: $config->treatEmptyAsNull,
        );
        $this->errorDetector = new ErrorResponseDetector();
        $this->xmlBuilder = new XmlRequestBuilder();
    }

    /**
     * Access the Customers endpoint.
     *
     * @return CustomersEndpoint Customers endpoint instance
     */
    public function customers(): CustomersEndpoint
    {
        return $this->customersEndpoint ??= $this->createEndpoint(CustomersEndpoint::class);
    }

    /**
     * Access the Items endpoint.
     *
     * @return ItemsEndpoint Items endpoint instance
     */
    public function items(): ItemsEndpoint
    {
        return $this->itemsEndpoint ??= $this->createEndpoint(ItemsEndpoint::class);
    }

    /**
     * Create an endpoint instance with all dependencies.
     *
     * @template T of EndpointInterface
     *
     * @param  class-string<T>  $class  Endpoint class name
     * @return T Endpoint instance
     */
    private function createEndpoint(string $class): EndpointInterface
    {
        return new $class(
            $this->config,
            $this->transport,
            $this->schemaRegistry,
            $this->parser,
            $this->errorDetector,
            $this->xmlBuilder,
        );
    }

    /**
     * Get the current configuration.
     *
     * Useful for debugging or extending functionality.
     *
     * @return Config SDK configuration
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get the transport layer.
     *
     * Useful for advanced use cases or testing.
     *
     * @return TransportInterface HTTP transport
     */
    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * Get the schema registry.
     *
     * Useful for registering custom schemas at runtime.
     *
     * @return SchemaRegistry Schema registry instance
     *
     * @example Register custom schema
     * ```php
     * $client->getSchemaRegistry()->register(
     *     'order',
     *     'ws_tellimused.xsd',
     *     'https://login.directo.ee/xmlcore/demo/ws_tellimused.xsd'
     * );
     * ```
     */
    public function getSchemaRegistry(): SchemaRegistry
    {
        return $this->schemaRegistry;
    }
}
