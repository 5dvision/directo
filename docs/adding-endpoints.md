# Adding New Endpoints

This guide explains how to add new endpoints to the SDK.

## Overview

Each endpoint is a single class that defines everything:
- API parameter (`what`)
- Allowed filters
- XML structure for PUT operations
- Schema files for validation

**No external registry updates needed!**

## Step 1: Create Endpoint Class

Create a new class in `src/Endpoint/`:

```php
<?php

declare(strict_types=1);

namespace Directo\Endpoint;

final class OrdersEndpoint extends AbstractEndpoint
{
    public function what(): string
    {
        return 'order';  // The API "what" parameter value
    }

    public function allowedFilters(): array
    {
        return [
            'number',
            'customer',
            'status',
            'ts',
        ];
    }

    public function xmlElements(): array
    {
        return [
            'root' => 'tellimused',    // Root XML element for PUT
            'record' => 'tellimus',    // Record element name
            'key' => 'number',         // Key attribute (null if no key)
        ];
    }

    public function schemas(): array
    {
        return [
            'list' => 'ws_tellimused.xsd',      // Schema for list() responses
            'put' => 'xml_IN_tellimused.xsd',   // Schema for put() requests
        ];
    }
}
```

## Step 2: Add Client Method

Add a method to `Client`:

```php
// In Client.php

private ?OrdersEndpoint $ordersEndpoint = null;

public function orders(): OrdersEndpoint
{
    return $this->ordersEndpoint ??= $this->createEndpoint(OrdersEndpoint::class);
}
```

That's it! The endpoint is ready to use.

## EndpointInterface Methods

The `EndpointInterface` requires these methods:

| Method | Purpose | Example |
|--------|---------|---------|
| `what()` | API parameter value | `'order'` |
| `allowedFilters()` | Valid filter keys | `['number', 'customer']` |
| `xmlElements()` | XML structure for PUT | `['root' => 'tellimused', ...]` |
| `schemas()` | Schema files per operation | `['list' => 'ws_tellimused.xsd']` |

Plus inherited operations: `list()`, `put()`, `putBatch()`

## Complete Example

### OrdersEndpoint.php

```php
<?php

declare(strict_types=1);

namespace Directo\Endpoint;

final class OrdersEndpoint extends AbstractEndpoint
{
    public function what(): string
    {
        return 'order';
    }

    public function allowedFilters(): array
    {
        return ['number', 'customer', 'date_from', 'date_to', 'ts'];
    }

    public function xmlElements(): array
    {
        return [
            'root' => 'tellimused',
            'record' => 'tellimus',
            'key' => 'number',
        ];
    }

    public function schemas(): array
    {
        return [
            'list' => 'ws_tellimused.xsd',
            'put' => 'xml_IN_tellimused.xsd',
        ];
    }

    /**
     * Get orders by customer.
     */
    public function byCustomer(string $customerCode): array
    {
        return $this->list(['customer' => $customerCode]);
    }
}
```

### Usage

```php
$client = new Client($config);

// List all orders
$orders = $client->orders()->list();

// List by customer
$orders = $client->orders()->byCustomer('CUST001');

// Create order
$result = $client->orders()->put([
    'number' => 'ORD001',
    'customer' => 'CUST001',
    'date' => '2024-01-15',
]);

// Batch create
$result = $client->orders()->putBatch([
    ['number' => 'ORD002', 'customer' => 'CUST001'],
    ['number' => 'ORD003', 'customer' => 'CUST002'],
]);
```

## XML Elements Reference

The `xmlElements()` method returns:

| Key | Type | Description |
|-----|------|-------------|
| `root` | string | Root element wrapping all records |
| `record` | string | Element name for each record |
| `key` | string\|null | Attribute name for record key (null if none) |

### Example XML Output

Given:

```php
public function xmlElements(): array
{
    return [
        'root' => 'tellimused',
        'record' => 'tellimus',
        'key' => 'number',
    ];
}
```

PUT with `['number' => 'ORD001', 'customer' => 'CUST001']` produces:

```xml
<tellimused>
  <tellimus number="ORD001">
    <customer>CUST001</customer>
  </tellimus>
</tellimused>
```

## Schemas Reference

The `schemas()` method returns schema files keyed by operation:

| Key | Purpose | Example |
|-----|---------|---------|
| `list` | Validate list() responses | `'ws_tellimused.xsd'` |
| `put` | Validate put() requests | `'xml_IN_tellimused.xsd'` |

Return `null` to skip validation for an operation:

```php
public function schemas(): array
{
    return [
        'list' => 'ws_tellimused.xsd',
        'put' => null,  // No input schema available
    ];
}
```

## Testing New Endpoints

```php
test('orders endpoint what value', function () {
    $endpoint = createEndpoint(OrdersEndpoint::class);
    expect($endpoint->what())->toBe('order');
});

test('orders schemas', function () {
    $endpoint = createEndpoint(OrdersEndpoint::class);
    expect($endpoint->schemas())->toBe([
        'list' => 'ws_tellimused.xsd',
        'put' => 'xml_IN_tellimused.xsd',
    ]);
});

test('lists orders', function () {
    $xml = '<results><order><number>ORD001</number></order></results>';
    $client = mockClient($xml);
    
    $orders = $client->orders()->list();
    
    expect($orders)->toHaveCount(1);
    expect($orders[0]['number'])->toBe('ORD001');
});
```

## Checklist

- [ ] Create endpoint class extending `AbstractEndpoint`
- [ ] Implement `what()` returning the API parameter value
- [ ] Implement `allowedFilters()` with valid filter keys
- [ ] Implement `xmlElements()` returning root/record/key config
- [ ] Implement `schemas()` returning schema files per operation
- [ ] Add custom methods for common queries (optional)
- [ ] Add client method to `Client`
- [ ] Write unit tests
- [ ] Add documentation in `docs/endpoints/`

## See Also

- [Items Endpoint](endpoints/items.md)
- [Customers Endpoint](endpoints/customers.md)
- [Schema Validation](schema-validation.md)
