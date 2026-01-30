<?php

declare(strict_types=1);

use Directo\Config;
use Directo\Endpoint\CustomersEndpoint;
use Directo\Endpoint\ItemsEndpoint;
use Directo\Exception\InvalidFilterException;
use Directo\Http\Transporter;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;



describe('Filter Validation', function (): void {
    test('CustomersEndpoint allows valid filters', function (): void {
        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $client = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);
        $endpoint = createEndpoint(CustomersEndpoint::class, $config, $transport);

        // Should not throw
        $result = $endpoint->list([
            'code' => 'CUST001',
            'closed' => 0,
        ]);

        expect($result)->toBeArray();
    });

    test('CustomersEndpoint rejects unknown filters', function (): void {
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config);
        $endpoint = createEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list(['unknown_filter' => 'value']);
    })->throws(InvalidFilterException::class, 'Unknown filter(s) [unknown_filter]');

    test('ItemsEndpoint allows valid filters', function (): void {
        $mock = new MockHandler([
            new Response(200, [], fixture('items.xml')),
        ]);

        $client = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);
        $endpoint = createEndpoint(ItemsEndpoint::class, $config, $transport);

        $result = $endpoint->list([
            'class' => 'ELECTRONICS',
            'code' => 'ITEM001',
        ]);

        expect($result)->toBeArray();
    });

    test('ItemsEndpoint rejects unknown filters', function (): void {
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config);
        $endpoint = createEndpoint(ItemsEndpoint::class, $config, $transport);

        $endpoint->list(['invalid' => 'filter', 'also_invalid' => 'value']);
    })->throws(InvalidFilterException::class, 'Unknown filter(s) [invalid, also_invalid]');

    test('rejects non-scalar filter values', function (): void {
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config);
        $endpoint = createEndpoint(CustomersEndpoint::class, $config, $transport);

        $endpoint->list(['code' => ['array', 'value']]);
    })->throws(InvalidFilterException::class, 'must be scalar or Stringable');

    test('accepts Stringable filter values', function (): void {
        $mock = new MockHandler([
            new Response(200, [], fixture('customers.xml')),
        ]);

        $client = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);
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

    test('exception contains context', function (): void {
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config);
        $endpoint = createEndpoint(CustomersEndpoint::class, $config, $transport);

        try {
            $endpoint->list(['bad_filter' => 'value']);
        } catch (InvalidFilterException $invalidFilterException) {
            expect($invalidFilterException->getContext())->toHaveKey('endpoint');
            expect($invalidFilterException->getContext()['unknown_filters'])->toContain('bad_filter');
            expect($invalidFilterException->getContext()['allowed_filters'])->toContain('code');
        }
    });
});
