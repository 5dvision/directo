<?php

declare(strict_types=1);

use Directo\Config;
use Directo\Endpoint\CustomersEndpoint;
use Directo\Endpoint\ItemsEndpoint;
use Directo\Exception\InvalidFilterException;
use Directo\Parser\ErrorResponseDetector;
use Directo\Parser\XmlRequestBuilder;
use Directo\Parser\XmlResponseParser;
use Directo\Schema\SchemaRegistry;
use Directo\Transport\Transport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function createEndpoint(string $class, Config $config, Transport $transport): mixed
{
    $schemaRegistry = new SchemaRegistry(
        $config->getSchemaBasePath(),
        $config->schemaBaseUrl,
    );
    $parser = new XmlResponseParser($config->treatEmptyAsNull);
    $errorDetector = new ErrorResponseDetector();
    $xmlBuilder = new XmlRequestBuilder();

    return new $class($config, $transport, $schemaRegistry, $parser, $errorDetector, $xmlBuilder);
}

describe('Filter Validation', function () {
    test('CustomersEndpoint allows valid filters', function () {
        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);
        $endpoint = createEndpoint(CustomersEndpoint::class, $config, $transport);

        // Should not throw
        $result = $endpoint->list([
            'code' => 'CUST001',
            'closed' => 0,
        ]);

        expect($result)->toBeArray();
    });

    test('CustomersEndpoint rejects unknown filters', function () {
        $config = new Config(token: 'test-key');
        $transport = new Transport($config);
        $endpoint = createEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list(['unknown_filter' => 'value']);
    })->throws(InvalidFilterException::class, 'Unknown filter(s) [unknown_filter]');

    test('ItemsEndpoint allows valid filters', function () {
        $mock = new MockHandler([
            new Response(200, [], fixture('items.xml')),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);
        $endpoint = createEndpoint(ItemsEndpoint::class, $config, $transport);

        $result = $endpoint->list([
            'class' => 'ELECTRONICS',
            'code' => 'ITEM001',
        ]);

        expect($result)->toBeArray();
    });

    test('ItemsEndpoint rejects unknown filters', function () {
        $config = new Config(token: 'test-key');
        $transport = new Transport($config);
        $endpoint = createEndpoint(ItemsEndpoint::class, $config, $transport);

        $endpoint->list(['invalid' => 'filter', 'also_invalid' => 'value']);
    })->throws(InvalidFilterException::class, 'Unknown filter(s) [invalid, also_invalid]');

    test('rejects non-scalar filter values', function () {
        $config = new Config(token: 'test-key');
        $transport = new Transport($config);
        $endpoint = createEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list(['code' => ['array', 'value']]);
    })->throws(InvalidFilterException::class, 'must be scalar or Stringable');

    test('accepts Stringable filter values', function () {
        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);
        $endpoint = createEndpoint(CustomersEndpoint::class, $config, $transport);

        $stringable = new class () implements Stringable {
            public function __toString(): string
            {
                return 'CUST001';
            }
        };

        $result = $endpoint->list(['code' => $stringable]);

        expect($result)->toBeArray();
    });

    test('exception contains context', function () {
        $config = new Config(token: 'test-key');
        $transport = new Transport($config);
        $endpoint = createEndpoint(CustomersEndpoint::class, $config, $transport);

        try {
            $endpoint->list(['bad_filter' => 'value']);
        } catch (InvalidFilterException $e) {
            expect($e->getContext())->toHaveKey('endpoint');
            expect($e->getContext()['unknown_filters'])->toContain('bad_filter');
            expect($e->getContext()['allowed_filters'])->toContain('code');
        }
    });
});
