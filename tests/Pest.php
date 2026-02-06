<?php

declare(strict_types=1);

use Directo\Config;
use Directo\Http\ErrorResponseDetector;
use Directo\Http\RequestBuilder;
use Directo\Http\ResponseParser;
use Directo\Http\Transporter;
use Directo\Schema\SchemaRegistry;

function fixture(string $name): string
{
    $path = __DIR__ . '/Fixtures/' . $name;
    if (! file_exists($path)) {
        throw new RuntimeException('Fixture not found: ' . $path);
    }

    return file_get_contents($path);
}

function createEndpoint(string $class, ?Config $config = null, ?Transporter $transport = null): mixed
{
    $config ??= new Config(token: 'test');
    if ($transport === null) {
        $mock = new \GuzzleHttp\Handler\MockHandler([]);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
        $httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);
        $transport = new Transporter($config, $httpClient);
    }
    $schemaRegistry = new SchemaRegistry(
        $config->getSchemaBasePath(),
        $config->schemaBaseUrl,
    );
    $parser = new ResponseParser($config->treatEmptyAsNull);
    $errorDetector = new ErrorResponseDetector();
    $xmlBuilder = new RequestBuilder();

    return new $class($config, $transport, $schemaRegistry, $parser, $errorDetector, $xmlBuilder);
}
