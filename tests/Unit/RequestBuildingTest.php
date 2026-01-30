<?php

declare(strict_types=1);

use Directo\Config;
use Directo\Endpoint\CustomersEndpoint;
use Directo\Endpoint\ItemsEndpoint;
use Directo\Http\ErrorResponseDetector;
use Directo\Http\RequestBuilder;
use Directo\Http\ResponseParser;
use Directo\Http\Transporter;
use Directo\Schema\SchemaRegistry;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function makeEndpoint(string $class, Config $config, Transporter $transport): mixed
{
    $schemaRegistry = new SchemaRegistry(
        $config->getSchemaBasePath(),
        $config->schemaBaseUrl,
    );
    $parser = new ResponseParser($config->treatEmptyAsNull);
    $errorDetector = new ErrorResponseDetector();
    $xmlBuilder = new RequestBuilder();

    return new $class($config, $transport, $schemaRegistry, $parser, $errorDetector, $xmlBuilder);
}

describe('Request Building', function (): void {
    test('includes get=1 and what parameter', function (): void {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new GuzzleClient(['handler' => $stack]);
        $config = new Config(token: 'secret-key');
        $transport = new Transporter($config, $client);
        $endpoint = makeEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list();

        expect($history)->toHaveCount(1);

        $request = $history[0]['request'];
        $body = (string) $request->getBody();

        expect($body)->toContain('get=1');
        expect($body)->toContain('what=customer');
    });

    test('includes token parameter with configured name', function (): void {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new GuzzleClient(['handler' => $stack]);
        $config = new Config(
            token: 'my-secret-token',
            tokenParamName: 'appkey',
        );
        $transport = new Transporter($config, $client);
        $endpoint = makeEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list();

        $body = (string) $history[0]['request']->getBody();
        expect($body)->toContain('appkey=my-secret-token');
    });

    test('includes filters in request', function (): void {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new GuzzleClient(['handler' => $stack]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);
        $endpoint = makeEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list([
            'code' => 'CUST001',
            'closed' => 0,
        ]);

        $body = (string) $history[0]['request']->getBody();
        expect($body)->toContain('code=CUST001');
        expect($body)->toContain('closed=0');
    });

    test('sends POST request with correct content type', function (): void {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('items.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new GuzzleClient(['handler' => $stack]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);
        $endpoint = makeEndpoint(ItemsEndpoint::class, $config, $transport);

        $endpoint->list();

        $request = $history[0]['request'];
        expect($request->getMethod())->toBe('POST');
        expect($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded');
    });

    test('sends to correct URL', function (): void {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new GuzzleClient(['handler' => $stack]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);
        $endpoint = makeEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list();

        $request = $history[0]['request'];
        expect((string) $request->getUri())->toBe('https://login.directo.ee/xmlcore/cap_xml_direct/xmlcore.asp');
    });

    test('ItemsEndpoint uses what=item', function (): void {
        $history = [];
        $historyMiddleware = Middleware::history($history);

        $mock = new MockHandler([
            new Response(200, [], fixture('items.xml')),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($historyMiddleware);

        $client = new GuzzleClient(['handler' => $stack]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);
        $endpoint = makeEndpoint(ItemsEndpoint::class, $config, $transport);

        $endpoint->list(['class' => 'ELECTRONICS']);

        $body = (string) $history[0]['request']->getBody();
        expect($body)->toContain('what=item');
        expect($body)->toContain('class=ELECTRONICS');
    });
});
