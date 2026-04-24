# Extending Directo SDK

This guide explains how to add support for new Directo modules by creating custom `Endpoint` classes.

## Creating an Endpoint

To add a new endpoint, extend `Directo\Endpoint\AbstractEndpoint` and implement the required methods.

### Required Methods

1.  **`what()`**: Returns the Directo API "what" parameter (e.g., 'order', 'project').
2.  **`allowedFilters()`**: Returns an array of valid filter keys for `list()`.
3.  **`xmlElements()`**: Defines the XML structure for `put()` operations.
4.  **`schemas()`**: Returns XSD filenames for validation (or `null`).

For `put()` payloads, the SDK expects the record body to follow the Directo IN schema. Top-level record attributes should be passed via `@attributes`, while nested containers such as `datafields` remain normal nested arrays.

### Example: Orders Endpoint

```php
namespace Directo\Endpoint;

final class OrdersEndpoint extends AbstractEndpoint
{
    public function what(): string
    {
        return 'order';
    }

    public function allowedFilters(): array
    {
        return ['number', 'customer', 'ts'];
    }

    public function xmlElements(): array
    {
        return [
            'root' => 'orders',        // Root XML tag in Directo IN schema
            'record' => 'order',       // Record tag in Directo IN schema
            'key' => 'number',         // Unique key attribute when passed directly
        ];
    }

    public function schemas(): array
    {
        return [
            'list' => 'ws_tellimused.xsd',
            'put' => 'xml_IN_tellimused.xsd',
        ];
    }
    
    // Custom helper method
    public function byCustomer(string $code): array
    {
        return $this->list(['customer' => $code]);
    }
}
```

Example `put()` payload for that endpoint:

```php
$client->orders()->put([
    '@attributes' => [
        'number' => 'ORD001',
        'customer' => 'CUST001',
    ],
]);
```

## Registering in Client

Add a convenience method to your `Client` class (or a subclass provided by your application code if you can't modify the library directly, though technically you can just instantiate the endpoint manually).

```php
// In a custom Client or Helper
public function orders(): OrdersEndpoint
{
    return $this->createEndpoint(OrdersEndpoint::class);
}
```

## Validation

Don't forget to run `composer schemas:update` to download the XSD files if you've enabled schema validation.
