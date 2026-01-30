# Testing

This guide covers testing strategies for the SDK and your integration code.

## Test Framework

The SDK uses PestPHP for testing:

```bash
composer require --dev pestphp/pest
```

## Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Unit/ClientTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

## Unit Testing with Guzzle MockHandler

The SDK uses Guzzle for HTTP transport, so the best way to test is using Guzzle's `MockHandler`.

```php
use Directo\Client;
use Directo\Config;
use Directo\Http\Transporter;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

test('lists items', function () {
    // 1. Create the mock response
    $mockXml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <results>
      <item>
        <kood>ITEM001</kood>
        <nimetus>Test Product</nimetus>
      </item>
    </results>
    XML;

    $mock = new MockHandler([
        new Response(200, [], $mockXml),
    ]);

    // 2. Wrap it in a HandlerStack and Guzzle Client
    $handlerStack = HandlerStack::create($mock);
    $httpClient = new GuzzleClient(['handler' => $handlerStack]);

    // 3. Create Config and Transporter
    $config = new Config(token: 'test-token');
    $transport = new Transporter($config, $httpClient);

    // 4. Create the Directo Client
    $client = new Client($config, $transport);

    $items = $client->items()->list();

    expect($items)->toBeArray();
    expect($items[0]['kood'])->toBe('ITEM001');
});
```

## Testing PUT Operations

```php
test('creates item', function () {
    $mock = new MockHandler([
        new Response(200, [], '<results><ok/></results>'),
    ]);
    
    $handlerStack = HandlerStack::create($mock);
    $httpClient = new GuzzleClient(['handler' => $handlerStack]);
    
    $config = new Config(token: 'test-token');
    $transport = new Transporter($config, $httpClient);
    $client = new Client($config, $transport);

    $result = $client->items()->put([
        'kood' => 'ITEM001',
        'nimetus' => 'New Product',
    ]);

    expect($result)->toHaveKey('ok');
    
    // Verify sent request body
    $lastRequest = $mock->getLastRequest();
    exit($lastRequest->getBody()->getContents()); // Or inspect it
    $sentBody = urldecode((string) $lastRequest->getBody());
    expect($sentBody)->toContain('<artikkel kood="ITEM001">');
});
```

## Testing Error Handling

```php
use Directo\Exception\ApiErrorException;
use Directo\Exception\AuthenticationException;

test('handles auth error', function () {
    $errorXml = '<results><result type="5">Invalid token</result></results>';
    
    $mock = new MockHandler([
        new Response(200, [], $errorXml),
    ]);
    $httpClient = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
    
    $config = new Config(token: 'test-token');
    $transport = new Transporter($config, $httpClient);
    $client = new Client($config, $transport);

    expect(fn() => $client->items()->list())
        ->toThrow(AuthenticationException::class); // Note: AuthenticationException needs to be implemented/mapped if you have specific logic for it, otherwise it might be ApiErrorException
});
```

## Integration Testing

For real API testing (use sparingly):

```php
test('integration: lists items from real API', function () {
    $token = getenv('DIRECTO_TOKEN');
    
    if (!$token) {
        $this->markTestSkipped('DIRECTO_TOKEN not set');
    }

    $client = new Client($token);
    $items = $client->items()->list(['closed' => 0]);

    expect($items)->toBeArray();
})->group('integration');
```

Run integration tests:

```bash
DIRECTO_TOKEN=your-token ./vendor/bin/pest --group=integration
```

## Test Helpers

Create reusable test helpers:

```php
// tests/Helpers.php

use Directo\Client;
use Directo\Config;
use Directo\Http\Transporter;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function mockClient(string $responseXml): Client
{
    $mock = new MockHandler([
        new Response(200, [], $responseXml),
    ]);
    $httpClient = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
    
    $config = new Config(token: 'test-token');
    $transport = new Transporter($config, $httpClient);
    
    return new Client($config, $transport);
}

function itemsXml(array $items): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?><results>';
    foreach ($items as $item) {
        $xml .= '<item>';
        foreach ($item as $key => $value) {
            $xml .= "<{$key}>{$value}</{$key}>";
        }
        $xml .= '</item>';
    }
    return $xml . '</results>';
}
```

## Test Structure

Recommended test organization:

```
tests/
├── Pest.php
├── Helpers.php
├── Unit/
│   ├── ClientTest.php
│   ├── Parser/
│   │   ├── XmlResponseParserTest.php
│   │   ├── XmlRequestBuilderTest.php
│   │   └── ErrorResponseDetectorTest.php
│   ├── Endpoint/
│   │   ├── ItemsEndpointTest.php
│   │   └── CustomersEndpointTest.php
│   └── Schema/
│       └── SchemaRegistryTest.php
├── Feature/
│   ├── ListItemsTest.php
│   └── PutItemsTest.php
└── Integration/
    └── RealApiTest.php
```

## Code Coverage

```bash
# Generate coverage report
./vendor/bin/pest --coverage --coverage-html=coverage

# Minimum coverage threshold
./vendor/bin/pest --coverage --min=80
```

## See Also

- [Error Handling](error-handling.md)
- [PUT Operations](put-operations.md)
- [Adding New Endpoints](adding-endpoints.md)
