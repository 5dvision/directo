<?php

declare(strict_types=1);

use Directo\Config;
use Directo\DirectoClient;
use Directo\Endpoint\ReceiptsEndpoint;
use Directo\Exception\InvalidFilterException;
use Directo\Transport\Transport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

describe('ReceiptsEndpoint', function () {
    test('what returns receipt', function () {
        $config = new Config(token: 'test-key');
        $transport = new Transport($config);
        $endpoint = createEndpoint(ReceiptsEndpoint::class, $config, $transport);
        
        expect($endpoint->what())->toBe('receipt');
    });

    test('allowed filters', function () {
        $config = new Config(token: 'test-key');
        $transport = new Transport($config);
        $endpoint = createEndpoint(ReceiptsEndpoint::class, $config, $transport);
        
        expect($endpoint->allowedFilters())->toBe([
            'number',
            'date1',
            'date2',
            'ts',
        ]);
    });

    test('xml elements configuration', function () {
        $config = new Config(token: 'test-key');
        $transport = new Transport($config);
        $endpoint = createEndpoint(ReceiptsEndpoint::class, $config, $transport);
        
        expect($endpoint->xmlElements())->toBe([
            'root' => 'transport',
            'record' => 'receipt',
            'key' => 'number',
        ]);
    });

    test('schemas configuration', function () {
        $config = new Config(token: 'test-key');
        $transport = new Transport($config);
        $endpoint = createEndpoint(ReceiptsEndpoint::class, $config, $transport);
        
        expect($endpoint->schemas())->toBe([
            'list' => 'ws_laekumised.xsd',
            'put' => null,
        ]);
    });

    test('lists receipts', function () {
        $mock = new MockHandler([
            new Response(200, [], fixture('receipts.xml')),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $httpClient);
        $endpoint = createEndpoint(ReceiptsEndpoint::class, $config, $transport);

        $receipts = $endpoint->list();

        expect($receipts)->toHaveCount(2);
        expect($receipts[0]['@number'])->toBe('123456');
        expect($receipts[0]['@confirmed'])->toBe('1');
        
        // Verify rows are parsed correctly
        expect($receipts[0])->toHaveKey('rows');
        expect($receipts[0]['rows'])->toHaveKey('row');
        expect($receipts[0]['rows']['row'])->toBeArray();
        expect($receipts[0]['rows']['row'])->toHaveCount(2);
        expect($receipts[0]['rows']['row'][0]['@invoice'])->toBe('INV001');
        expect($receipts[0]['rows']['row'][0]['@order'])->toBe('1001');
        expect($receipts[0]['rows']['row'][1]['@invoice'])->toBe('INV002');
        expect($receipts[0]['rows']['row'][1]['@order'])->toBe('1002');
    });

    test('lists receipts with filters', function () {
        $mock = new MockHandler([
            new Response(200, [], fixture('receipts.xml')),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $httpClient);
        $endpoint = createEndpoint(ReceiptsEndpoint::class, $config, $transport);

        $receipts = $endpoint->list([
            'date1' => '2024-01-01',
            'date2' => '2024-01-31',
        ]);
		
        expect($receipts)->toHaveCount(2);
    });

    test('rejects invalid filters', function () {
        $config = new Config(token: 'test-key');
        $transport = new Transport($config);
        $endpoint = createEndpoint(ReceiptsEndpoint::class, $config, $transport);

        $endpoint->list(['invalid_filter' => 'value']);
    })->throws(InvalidFilterException::class);

    test('client provides access to receipts endpoint', function () {
        $config = new Config(token: 'test-key');
        $client = new DirectoClient($config);

        expect($client->receipts())->toBeInstanceOf(ReceiptsEndpoint::class);
    });

    test('client reuses receipts endpoint instance', function () {
        $config = new Config(token: 'test-key');
        $client = new DirectoClient($config);

        $receipts1 = $client->receipts();
        $receipts2 = $client->receipts();

        expect($receipts1)->toBe($receipts2);
    });

    test('fetches receipts with number filter', function () {
        $mock = new MockHandler([
            new Response(200, [], fixture('receipts.xml')),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $client = new DirectoClient($config, $httpClient);

        $receipts = $client->receipts()->list(['number' => 123456]);

        expect($receipts)->toHaveCount(2);
        expect($receipts[0]['@number'])->toBe('123456');
    });
});
