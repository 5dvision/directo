# Directo PHP SDK - AI Coding Instructions

## Architecture Overview

This is a PHP 8.2+ SDK for the Directo XMLCore API. Key design principles:

- **Immutable Config**: `Config` is a `final readonly` class - never mutate
- **Lazy Endpoints**: `DirectoClient` instantiates endpoints on first access via `createEndpoint()`
- **Template Pattern**: All endpoints extend `AbstractEndpoint` which handles HTTP, parsing, validation
- **Self-Contained Endpoints**: Each endpoint defines `what()`, `allowedFilters()`, `xmlElements()`, `schemas()` - no external registry

## Key Patterns

### Adding a New Endpoint

1. Create class in `src/Endpoint/` extending `AbstractEndpoint`
2. Implement the 4 required methods (see `CustomersEndpoint` as example)
3. Add accessor method in `DirectoClient` using `createEndpoint()`
4. Run `composer schemas:update` to download XSD files

```php
// DirectoClient.php - use the factory pattern
public function newEndpoint(): NewEndpoint
{
    return $this->newEndpoint ??= $this->createEndpoint(NewEndpoint::class);
}
```

### Testing with Guzzle MockHandler

Tests use PestPHP with a `fixture()` helper. Always mock HTTP responses:

```php
$mock = new MockHandler([
    new Response(200, [], fixture('customers.xml')),
]);
$client = new Client(['handler' => HandlerStack::create($mock)]);
$config = new Config(token: 'test-key');
$transport = new Transport($config, $client);
```

Fixtures live in `tests/Fixtures/` as XML files.

### Exception Hierarchy

All exceptions extend `DirectoException` with context (no auth data):
- `TransportException` - Network/connection errors
- `HttpException` - Non-2xx responses (has `getStatusCode()`, `getResponseBody()`)
- `ApiErrorException` - Directo XML error responses (HTTP 200 but error content)
- `InvalidFilterException` - Unknown filter keys (developer error)
- `SchemaValidationException` - XSD validation failure

## Commands

```bash
composer test              # Run PestPHP tests
composer schemas:update    # Download XSD schemas from Directo
./vendor/bin/pint         # Format code with Laravel Pint (PSR-12)
```

## Code Conventions

- **Strict types**: Every file starts with `declare(strict_types=1);`
- **Final classes**: All non-abstract classes are `final`
- **Constructor promotion**: Use PHP 8 constructor property promotion
- **No credentials in logs/exceptions**: Transport redacts token, exceptions exclude auth
- **PHPDoc on class properties**: Use `/** @var Type Description */` inline format

## File Structure

```
src/
├── Config.php              # Immutable configuration (DEFAULT_* constants)
├── DirectoClient.php       # Main entry point, endpoint factory
├── Endpoint/
│   ├── EndpointInterface.php
│   ├── AbstractEndpoint.php  # Template: list(), put(), putBatch()
│   ├── CustomersEndpoint.php # Example endpoint
│   └── ItemsEndpoint.php
├── Transport/              # HTTP layer (Guzzle wrapper)
├── Parser/                 # XML parsing, error detection, request building
├── Schema/                 # XSD validation, schema downloading
└── Exception/              # All SDK exceptions
```

## Schema System

- Schemas defined per endpoint via `schemas()` method
- Files stored in `resources/xsd/`
- `SchemaDownloader` auto-discovers endpoints via reflection
- Validation is optional (`Config::validateSchema = false` by default)
