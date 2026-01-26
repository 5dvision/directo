<?php

declare(strict_types=1);

use Directo\Config;
use Directo\Exception\HttpException;
use Directo\Exception\TransportException;
use Directo\Transport\Transport;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('Transport', function () {
    test('returns response body on success', function () {
        $mock = new MockHandler([
            new Response(200, [], '<results></results>'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);

        $result = $transport->post(['get' => 1, 'what' => 'test']);

        expect($result)->toBe('<results></results>');
    });

    test('throws HttpException on non-200 response', function () {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);

        $transport->post(['get' => 1]);
    })->throws(HttpException::class, 'HTTP request failed with status 500');

    test('HttpException contains status code and body', function () {
        $mock = new MockHandler([
            new Response(404, [], 'Not Found'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);

        try {
            $transport->post(['get' => 1]);
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(404);
            expect($e->getResponseBody())->toBe('Not Found');
        }
    });

    test('throws TransportException on connection error', function () {
        $mock = new MockHandler([
            new ConnectException(
                'Connection refused',
                new Request('POST', 'https://test.example.com'),
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);

        $transport->post(['get' => 1]);
    })->throws(TransportException::class, 'Connection failed');

    test('TransportException wraps original exception', function () {
        $originalException = new ConnectException(
            'DNS resolution failed',
            new Request('POST', 'https://test.example.com'),
        );

        $mock = new MockHandler([$originalException]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);

        try {
            $transport->post(['get' => 1]);
        } catch (TransportException $e) {
            expect($e->getPrevious())->toBe($originalException);
        }
    });

    test('passes context to exception', function () {
        $mock = new MockHandler([
            new Response(500, [], 'Error'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transport($config, $client);

        $context = ['endpoint' => 'customers', 'what' => 'customer'];

        try {
            $transport->post(['get' => 1], $context);
        } catch (HttpException $e) {
            expect($e->getContext())->toBe($context);
        }
    });
});
