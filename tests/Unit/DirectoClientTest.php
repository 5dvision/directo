<?php

declare(strict_types=1);

use Directo\Config;
use Directo\DirectoClient;
use Directo\Endpoint\CustomersEndpoint;
use Directo\Endpoint\ItemsEndpoint;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

describe('DirectoClient', function () {
    test('provides access to customers endpoint', function () {
        $config = new Config(token: 'test-key');
        $client = new DirectoClient($config);

        expect($client->customers())->toBeInstanceOf(CustomersEndpoint::class);
    });

    test('provides access to items endpoint', function () {
        $config = new Config(token: 'test-key');
        $client = new DirectoClient($config);

        expect($client->items())->toBeInstanceOf(ItemsEndpoint::class);
    });

    test('reuses endpoint instances', function () {
        $config = new Config(token: 'test-key');
        $client = new DirectoClient($config);

        $customers1 = $client->customers();
        $customers2 = $client->customers();

        expect($customers1)->toBe($customers2);
    });

    test('exposes config', function () {
        $config = new Config(token: 'test-key');
        $client = new DirectoClient($config);

        expect($client->getConfig())->toBe($config);
    });

    test('fetches customers', function () {
        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $client = new DirectoClient($config, $httpClient);

        $customers = $client->customers()->list();

        expect($customers)->toHaveCount(2);
        expect($customers[0]['@code'])->toBe('CUST001');
    });

    test('fetches items with filters', function () {
        $mock = new MockHandler([
            new Response(200, [], fixture('items.xml')),
        ]);

        $httpClient = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $client = new DirectoClient($config, $httpClient);

        $items = $client->items()->list(['class' => 'ELECTRONICS']);

        expect($items)->toHaveCount(2);
        expect($items[0]['@name'])->toBe('Simple Service Item');
    });
});
