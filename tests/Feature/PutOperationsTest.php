<?php

declare(strict_types=1);

use Directo\Config;
use Directo\DirectoClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

describe('PUT Operations', function () {
    test('items put sends correct request parameters', function () {
        $responseXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<results>
    <result type="ok" what="item" code="ITEM001"/>
</results>
XML;

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $responseXml),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new DirectoClient(
            new Config(token: 'test-token'),
            $httpClient,
        );

        $result = $client->items()->put([
            'kood' => 'ITEM001',
            'nimetus' => 'Test Item',
            'hind' => 99.99,
        ]);

        // Verify request was made
        $lastRequest = $mock->getLastRequest();
        expect($lastRequest)->not->toBeNull();

        // Check request body contains put=1
        $body = (string) $lastRequest->getBody();
        expect($body)->toContain('put=1');
        expect($body)->toContain('what=item');
        expect($body)->toContain('xmldata=');

        // Check XML data is present (URL encoded)
        expect($body)->toContain(urlencode('<artiklid>'));
        expect($body)->toContain(urlencode('kood="ITEM001"'));
    });

    test('items putBatch sends multiple records', function () {
        $responseXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<results>
    <result type="ok" what="item" code="ITEM001"/>
    <result type="ok" what="item" code="ITEM002"/>
</results>
XML;

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $responseXml),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new DirectoClient(
            new Config(token: 'test-token'),
            $httpClient,
        );

        $result = $client->items()->putBatch([
            ['kood' => 'ITEM001', 'nimetus' => 'Item 1'],
            ['kood' => 'ITEM002', 'nimetus' => 'Item 2'],
        ]);

        $lastRequest = $mock->getLastRequest();
        $body = (string) $lastRequest->getBody();

        expect($body)->toContain('put=1');
        expect($body)->toContain(urlencode('ITEM001'));
        expect($body)->toContain(urlencode('ITEM002'));
    });

    test('customers put sends correct request', function () {
        $responseXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<results>
    <result type="ok" what="customer" code="CUST001"/>
</results>
XML;

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $responseXml),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new DirectoClient(
            new Config(token: 'test-token'),
            $httpClient,
        );

        $result = $client->customers()->put([
            'kood' => 'CUST001',
            'nimi' => 'Test Customer',
        ]);

        $lastRequest = $mock->getLastRequest();
        $body = (string) $lastRequest->getBody();

        expect($body)->toContain('put=1');
        expect($body)->toContain('what=customer');
        expect($body)->toContain(urlencode('<kliendid>'));
        expect($body)->toContain(urlencode('kood="CUST001"'));
    });

    test('put detects API error response', function () {
        $responseXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<results>
    <error desc="Invalid item code"/>
</results>
XML;

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/xml'], $responseXml),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new DirectoClient(
            new Config(token: 'test-token'),
            $httpClient,
        );

        $client->items()->put([
            'kood' => 'INVALID',
            'nimetus' => 'Test',
        ]);
    })->throws(\Directo\Exception\ApiErrorException::class);
});
