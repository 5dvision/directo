<?php

declare(strict_types=1);

namespace Directo;

use Directo\Endpoint\CustomersEndpoint;
use Directo\Contract\Endpoint;
use Directo\Endpoint\ItemsEndpoint;
use Directo\Endpoint\ReceiptsEndpoint;
use Directo\Http\ErrorResponseDetector;
use Directo\Http\RequestBuilder;
use Directo\Http\ResponseParser;
use Directo\Http\Transporter;
use Directo\Contract\Transporter as TransporterContract;
use Directo\Schema\SchemaRegistry;
use Directo\Endpoint\XmlCoreEndpoint;

/**
 * Directo XMLCore API client.
 *
 * Main entry point for the SDK. Provides access to all endpoints.
 *
 * @example Basic usage
 * ```php
 * $client = new Client(new Config(
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
 * $client = new Client(new Config(
 *     token: 'your-api-token',
 *     validateSchema: true, // Enable XSD validation
 * ));
 * ```
 * @example With PSR-3 logger
 * ```php
 * $client = new Client(
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
final class Client
{
    /** @var TransporterContract HTTP transport layer */
    private readonly TransporterContract $transport;

    /** @var SchemaRegistry XSD schema validator */
    private readonly SchemaRegistry $schemaRegistry;

    /** @var ResponseParser XML response parser */
    private readonly ResponseParser $parser;

    /** @var ErrorResponseDetector API error detector */
    private readonly ErrorResponseDetector $errorDetector;

    /** @var RequestBuilder XML request builder */
    private readonly RequestBuilder $xmlBuilder;

    /** @var CustomersEndpoint|null Cached customers endpoint */
    private ?CustomersEndpoint $customersEndpoint = null;

    /** @var ItemsEndpoint|null Cached items endpoint */
    private ?ItemsEndpoint $itemsEndpoint = null;

    /** @var ReceiptsEndpoint|null Cached receipts endpoint */
    private ?ReceiptsEndpoint $receiptsEndpoint = null;

    /**
     * Create a new Directo client.
     *
     * @param  Config  $config  SDK configuration
     * @param  TransporterContract|null  $transport  Optional Transport implementation
     */
    public function __construct(
        private readonly Config $config,
        ?TransporterContract $transport = null,
    ) {
        $this->transport = $transport ?? new Transporter($config);
        $this->schemaRegistry = new SchemaRegistry(
            $config->getSchemaBasePath(),
            $config->schemaBaseUrl,
        );
        $this->parser = new ResponseParser(
            treatEmptyAsNull: $config->treatEmptyAsNull,
        );
        $this->errorDetector = new ErrorResponseDetector();
        $this->xmlBuilder = new RequestBuilder();
    }

    /**
     * Returns Customers endpoint instance.
     *
     * @return CustomersEndpoint
     */
    public function customers(): CustomersEndpoint
    {
        return $this->customersEndpoint ??= $this->createEndpoint(CustomersEndpoint::class);
    }

    /**
     * Returns Items endpoint instance.
     *
     * @return ItemsEndpoint
     */
    public function items(): ItemsEndpoint
    {
        return $this->itemsEndpoint ??= $this->createEndpoint(ItemsEndpoint::class);
    }

    /**
     * Returns Receipts endpoint instance.
     *
     * @return ReceiptsEndpoint
     */
    public function receipts(): ReceiptsEndpoint
    {
        return $this->receiptsEndpoint ??= $this->createEndpoint(ReceiptsEndpoint::class);
    }

    /**
     * Returns a generic XmlCore endpoint.
     *
     * @param  string  $what  The endpoint name (e.g. 'invoice')
     * @return XmlCoreEndpoint
     */
    public function xmlCore(string $what): XmlCoreEndpoint
    {
        /** @var XmlCoreEndpoint $endpoint */
        $endpoint = $this->createEndpoint(XmlCoreEndpoint::class);
        $endpoint->setWhat($what);

        return $endpoint;
    }

    /**
     * Creates an endpoint instance with all dependencies.
     *
     * @template T of Endpoint
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function createEndpoint(string $class): Endpoint
    {
        /** @var Endpoint $endpoint */
        $endpoint = new $class(
            $this->config,
            $this->transport,
            $this->schemaRegistry,
            $this->parser,
            $this->errorDetector,
            $this->xmlBuilder,
        );

        return $endpoint;
    }

    /**
     * Returns the current configuration.
     *
     * Useful for debugging or extending functionality.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Returns the transport layer.
     *
     * Useful for advanced use cases or testing.
     *
     * @return TransporterContract
     */
    public function getTransport(): TransporterContract
    {
        return $this->transport;
    }

    /**
     * Returns the schema registry.
     *
     * Useful for registering custom schemas at runtime.
     *
     * @return SchemaRegistry
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
