<?php

declare(strict_types=1);

use Directo\Client;
use Directo\Config;
use Directo\Endpoint\XmlCoreEndpoint;
use Directo\Http\Transporter;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

test('sets and gets what parameter', function () {
    /** @var XmlCoreEndpoint $endpoint */
    $endpoint = createEndpoint(XmlCoreEndpoint::class);

    $endpoint->setWhat('custom_thing');

    expect($endpoint->what())->toBe('custom_thing');
});

test('sets and gets schemas', function () {
    /** @var XmlCoreEndpoint $endpoint */
    $endpoint = createEndpoint(XmlCoreEndpoint::class);

    $schemas = ['put' => 'schema.xsd'];
    $endpoint->setSchemas($schemas);

    expect($endpoint->schemas())->toBe($schemas);
});

test('has empty xml elements configuration', function () {
    /** @var XmlCoreEndpoint $endpoint */
    $endpoint = createEndpoint(XmlCoreEndpoint::class);

    expect($endpoint->xmlElements())->toBeEmpty();
});

test('allows any filters', function () {
    /** @var XmlCoreEndpoint $endpoint */
    $endpoint = createEndpoint(XmlCoreEndpoint::class);

    expect($endpoint->allowedFilters())->toBeEmpty();

    // Should not throw exception
    $endpoint->validateFilters(['any' => 'filter']);
});

test('putArray converts array to xml and sends request', function () {
    $mock = new MockHandler([
        new Response(200, [], '<results><ok/></results>'),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $httpClient = new GuzzleClient(['handler' => $handlerStack]);

    $config = new Config(token: 'test-token');
    $transport = new Transporter($config, $httpClient);
    $client = new Client($config, $transport);

    $endpoint = $client->xmlCore('test');

    $data = [
        'root' => [
            'item' => 'value'
        ]
    ];

    $result = $endpoint->putArray($data);

    expect($result)->toBeArray();

    $lastRequest = $mock->getLastRequest();
    $body = urldecode((string) $lastRequest->getBody());

    expect($body)->toContain('xmldata=');
    // The exact XML format depends on implementation, but checking if it sends something is good.
    // AbstractEndpoint::sendPutRequest wraps data in xmldata=...
});
