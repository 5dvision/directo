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

## Unit Testing with Mock Transport

Use `MockTransport` to test without hitting the real API:

```php
use Directo\DirectoClient;
use Directo\Transport\MockTransport;

test('lists items', function () {
    $mockXml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <results>
      <item>
        <kood>ITEM001</kood>
        <nimetus>Test Product</nimetus>
      </item>
    </results>
    XML;

    $transport = new MockTransport($mockXml);
    $client = DirectoClient::withTransport($transport);

    $items = $client->items()->list();

    expect($items)->toBeArray();
    expect($items[0]['kood'])->toBe('ITEM001');
});
```

## Testing PUT Operations

```php
test('creates item', function () {
    $transport = new MockTransport('<results><ok/></results>');
    $client = DirectoClient::withTransport($transport);

    $result = $client->items()->put([
        'kood' => 'ITEM001',
        'nimetus' => 'New Product',
    ]);

    expect($result)->toHaveKey('ok');
    
    // Verify sent XML
    $sentBody = $transport->getLastRequestBody();
    expect($sentBody)->toContain('<artikkel kood="ITEM001">');
});
```

## Testing Error Handling

```php
use Directo\Exception\ApiErrorException;
use Directo\Exception\AuthenticationException;

test('handles auth error', function () {
    $errorXml = '<results><result type="5">Invalid token</result></results>';
    $transport = new MockTransport($errorXml);
    $client = DirectoClient::withTransport($transport);

    expect(fn() => $client->items()->list())
        ->toThrow(AuthenticationException::class);
});

test('handles API error', function () {
    $errorXml = '<error desc="Item not found"/>';
    $transport = new MockTransport($errorXml);
    $client = DirectoClient::withTransport($transport);

    expect(fn() => $client->items()->list())
        ->toThrow(ApiErrorException::class);
});
```

## Testing with Guzzle MockHandler

For more control over HTTP responses:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Directo\Transport\GuzzleTransport;

test('handles HTTP error', function () {
    $mock = new MockHandler([
        new Response(500, [], 'Internal Server Error'),
    ]);
    
    $handlerStack = HandlerStack::create($mock);
    $httpClient = new Client(['handler' => $handlerStack]);
    
    $transport = new GuzzleTransport($httpClient, 'test-token');
    $client = DirectoClient::withTransport($transport);

    expect(fn() => $client->items()->list())
        ->toThrow(TransportException::class);
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

    $client = new DirectoClient($token);
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

function mockClient(string $responseXml): DirectoClient
{
    $transport = new MockTransport($responseXml);
    return DirectoClient::withTransport($transport);
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
