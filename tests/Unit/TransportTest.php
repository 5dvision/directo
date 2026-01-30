<?php

declare(strict_types=1);

use Directo\Config;
use Directo\Exception\HttpException;
use Directo\Exception\TransportException;
use Directo\Http\Transporter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('Transporter', function (): void {
    test('returns response body on success', function (): void {
        $mock = new MockHandler([
            new Response(200, [], '<results></results>'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);

        $result = $transport->post(['get' => 1, 'what' => 'test']);

        expect($result)->toBe('<results></results>');
    });

    test('throws HttpException on non-200 response', function (): void {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);

        $transport->post(['get' => 1]);
    })->throws(HttpException::class, 'HTTP request failed with status 500');

    test('HttpException contains status code and body', function (): void {
        $mock = new MockHandler([
            new Response(404, [], 'Not Found'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);

        try {
            $transport->post(['get' => 1]);
        } catch (HttpException $httpException) {
            expect($httpException->getStatusCode())->toBe(404);
            expect($httpException->getResponseBody())->toBe('Not Found');
        }
    });

    test('throws TransportException on connection error', function (): void {
        $mock = new MockHandler([
            new ConnectException(
                'Connection refused',
                new Request('POST', 'https://test.example.com'),
            ),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);

        $transport->post(['get' => 1]);
    })->throws(TransportException::class, 'Connection failed');

    test('TransportException wraps original exception', function (): void {
        $originalException = new ConnectException(
            'DNS resolution failed',
            new Request('POST', 'https://test.example.com'),
        );

        $mock = new MockHandler([$originalException]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);

        try {
            $transport->post(['get' => 1]);
        } catch (TransportException $transportException) {
            expect($transportException->getPrevious())->toBe($originalException);
        }
    });

    test('passes context to exception', function (): void {
        $mock = new MockHandler([
            new Response(500, [], 'Error'),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = new Config(token: 'test-key');
        $transport = new Transporter($config, $client);

        $context = ['endpoint' => 'customers', 'what' => 'customer'];

        try {
            $transport->post(['get' => 1], $context);
        } catch (HttpException $httpException) {
            expect($httpException->getContext())->toBe($context);
        }
    });
});
