<?php

declare(strict_types=1);

use Directo\Config;
use Directo\Endpoint\CustomersEndpoint;
use Directo\Endpoint\ItemsEndpoint;
use Directo\Parser\ErrorResponseDetector;
use Directo\Parser\XmlRequestBuilder;
use Directo\Parser\XmlResponseParser;
use Directo\Schema\SchemaRegistry;
use Directo\Transport\Transport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function makeEndpoint(string $class, Config $config, Transport $transport): mixed
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

describe('Request Building', function () {
    test('includes get=1 and what parameter', function () {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new Client(['handler' => $stack]);
        $config = new Config(token: 'secret-key');
        $transport = new Transport($config, $client);
        $endpoint = makeEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list();

        expect($history)->toHaveCount(1);

        $request = $history[0]['request'];
        $body = (string) $request->getBody();

        expect($body)->toContain('get=1');
        expect($body)->toContain('what=customer');
    });

    test('includes token parameter with configured name', function () {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new Client(['handler' => $stack]);
        $config = new Config(
            token: 'my-secret-token',
            tokenParamName: 'appkey',
        );
        $transport = new Transport($config, $client);
        $endpoint = makeEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list();

        $body = (string) $history[0]['request']->getBody();
        expect($body)->toContain('appkey=my-secret-token');
    });

    test('includes filters in request', function () {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new Client(['handler' => $stack]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);
        $endpoint = makeEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list([
            'code' => 'CUST001',
            'closed' => 0,
        ]);

        $body = (string) $history[0]['request']->getBody();
        expect($body)->toContain('code=CUST001');
        expect($body)->toContain('closed=0');
    });

    test('sends POST request with correct content type', function () {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('items.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new Client(['handler' => $stack]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);
        $endpoint = makeEndpoint(ItemsEndpoint::class, $config, $transport);

        $endpoint->list();

        $request = $history[0]['request'];
        expect($request->getMethod())->toBe('POST');
        expect($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded');
    });

    test('sends to correct URL', function () {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new Client(['handler' => $stack]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);
        $endpoint = makeEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list();

        $request = $history[0]['request'];
        expect((string) $request->getUri())->toBe('https://login.directo.ee/xmlcore/cap_xml_direct/xmlcore.asp');
    });

    test('ItemsEndpoint uses what=item', function () {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('items.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new Client(['handler' => $stack]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);
        $endpoint = makeEndpoint(ItemsEndpoint::class, $config, $transport);

        $endpoint->list(['class' => 'ELECTRONICS']);

        $body = (string) $history[0]['request']->getBody();
        expect($body)->toContain('what=item');
        expect($body)->toContain('class=ELECTRONICS');
    });
});
