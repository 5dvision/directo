<?php

declare(strict_types=1);

use Directo\Config;
use Directo\Client;
use Directo\Endpoint\CustomersEndpoint;
use Directo\Endpoint\ItemsEndpoint;
use Directo\Http\Transporter;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

describe('Client', function (): void {
    test('provides access to customers endpoint', function (): void {
        $config = new Config(token: 'test-key');
        $client = new Client($config);

        expect($client->customers())->toBeInstanceOf(CustomersEndpoint::class);
    });

    test('provides access to items endpoint', function (): void {
        $config = new Config(token: 'test-key');
        $client = new Client($config);

        expect($client->items())->toBeInstanceOf(ItemsEndpoint::class);
    });

    test('reuses endpoint instances', function (): void {
        $config = new Config(token: 'test-key');
        $client = new Client($config);

        $customers1 = $client->customers();
        $customers2 = $client->customers();

        expect($customers1)->toBe($customers2);
    });

    test('exposes config', function (): void {
        $config = new Config(token: 'test-key');
        $client = new Client($config);

        expect($client->getConfig())->toBe($config);
    });

    test('fetches customers', function (): void {
        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $httpClient = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $httpClient);
        $client = new Client($config, $transport);

        $customers = $client->customers()->list();

        expect($customers)->toHaveCount(2);
        expect($customers[0]['@code'])->toBe('CUST001');
    });

    test('fetches items with filters', function (): void {
        $mock = new MockHandler([
            new Response(200, [], fixture('items.xml')),
        ]);

        $httpClient = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $httpClient);
        $client = new Client($config, $transport);

        $items = $client->items()->list(['class' => 'ELECTRONICS']);

        expect($items)->toHaveCount(2);
        expect($items[0]['@name'])->toBe('Simple Service Item');
    });
});
