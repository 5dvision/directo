# XML Structure

This guide explains how the SDK maps PHP arrays to Directo XML for `put()` and `putBatch()`, and how that differs from parsed `list()` responses.

## Core Rule

Do not use `list()` response field names as `put()` request keys.

- `list()` responses are parsed from XML attributes and come back with `@` prefixes such as `@code`, `@name`, and `@email`.
- `put()` and `putBatch()` requests must follow the Directo IN schema for that endpoint.
- Record attributes belong inside `@attributes`.

## Basic Pattern

```php
$client->customers()->put([
    '@attributes' => [
        'code' => 'CUST001',
        'name' => 'Acme OU',
        'email' => 'info@example.com',
    ],
]);
```

That becomes:

```xml
<customers>
  <customer code="CUST001" name="Acme OU" email="info@example.com"/>
</customers>
```

## Nested Containers

Use normal nested arrays for child containers and `@attributes` for each nested record.

```php
$client->customers()->put([
    '@attributes' => [
        'code' => 'CUST001',
        'name' => 'Acme OU',
    ],
    'datafields' => [
        'data' => [
            [
                '@attributes' => [
                    'code' => 'vip',
                    'content' => 'yes',
                    'param' => 'segment',
                ],
            ],
        ],
    ],
]);
```

## Endpoint Examples

### Customers

```php
$client->customers()->put([
    '@attributes' => [
        'code' => 'CUST001',
        'name' => 'Acme OU',
        'phone' => '+372555',
    ],
]);
```

### Items

```php
$client->items()->put([
    '@attributes' => [
        'code' => 'ITEM001',
        'name' => 'Demo Product',
        'class' => 'SERVICES',
        'salesprice' => 99.99,
    ],
]);
```

## Common Mistakes

- Using Estonian OUT names like `kood`, `nimi`, `nimetus`, or `hind` as PUT keys.
- Expecting parsed response fields like `@code` to be valid request keys.
- Forgetting that nested repeated records also need `@attributes`.
- Assuming Directo wiki examples match this SDK's normalized request structure.
