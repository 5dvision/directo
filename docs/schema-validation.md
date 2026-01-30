# Schema Validation

The SDK supports XSD schema validation for both input (PUT) and output (GET) operations.

## Overview

Directo provides XSD schemas that define the structure of XML requests and responses:

| Type | Pattern | Purpose |
|------|---------|---------|
| Output | `ws_*.xsd` | Validate API responses (list) |
| Input | `xml_IN_*.xsd` | Validate API requests (put) |

## Enabling Validation

Enable schema validation via Config:

```php
use Directo\Config;
use Directo\Client;

$client = new Client(new Config(
    token: 'your-api-token',
    validateSchema: true,  // Enable XSD validation
));
```

When enabled, the SDK validates:
- **list()** responses against the endpoint's `list` schema
- **put()** requests against the endpoint's `put` schema

## Endpoint-Defined Schemas

Each endpoint defines its own schemas via the `schemas()` method:

```php
// In ItemsEndpoint
public function schemas(): array
{
    return [
        'list' => 'ws_artiklid.xsd',      // For list() responses
        'put' => 'xml_IN_artiklid.xsd',   // For put() requests
    ];
}
```

This keeps all endpoint configuration in one place - no separate registry to update.

### Adding Schemas to New Endpoints

When creating a new endpoint, just implement `schemas()`:

```php
final class OrdersEndpoint extends AbstractEndpoint
{
    public function what(): string { return 'order'; }
    
    public function schemas(): array
    {
        return [
            'list' => 'ws_tellimused.xsd',
            'put' => 'xml_IN_tellimused.xsd',
        ];
    }
    
    // ... other methods
}
```

Return `null` or omit a key to skip validation for that operation:

```php
public function schemas(): array
{
    return [
        'list' => 'ws_tellimused.xsd',
        'put' => null,  // No input schema available
    ];
}
```

## Schema URLs

Schemas are fetched from the Directo server:

```
https://login.directo.ee/xmlcore/cap_xml_direct/{schema_file}
```

### Examples

| Endpoint | List Schema | Put Schema |
|----------|-------------|------------|
| Items | `ws_artiklid.xsd` | `xml_IN_artiklid.xsd` |
| Customers | `ws_kliendid.xsd` | `xml_IN_kliendid.xsd` |

## Downloading Schemas

Schemas must be downloaded before validation works:

```bash
composer schemas:update
```

Schemas are stored in `resources/xsd/` and should be committed to version control.

## Validation Errors

When validation fails, a `SchemaValidationException` is thrown:

```php
use Directo\Exception\SchemaValidationException;

try {
    $items = $client->items()->list();
} catch (SchemaValidationException $e) {
    echo "Validation failed: " . $e->getMessage();
    
    // Get detailed errors
    foreach ($e->getFormattedErrors() as $error) {
        echo "- " . $error . "\n";
    }
}
```

## Performance Considerations

- Schema validation adds overhead to each request (DOM parsing + XSD validation)
- **Disabled by default** for performance
- Enable for debugging, CI/CD, or batch jobs
- Consider enabling periodically to detect API drift

## See Also

- [Error Handling](error-handling.md)
- [Adding New Endpoints](adding-endpoints.md)
