---
name: directo-sdk
description: Expert guide for the 5dvision/directo PHP SDK. Use when the user needs to integrate with Directo ERP, query data (items, customers, etc.), create custom endpoints, debug XML issues, or understand SDK configuration.
---

# Directo SDK Expert

This skill provides expert knowledge for the `5dvision/directo` PHP SDK, a fluent interface for the Directo XMLCore API.

## When to Use

Use this skill when the user asks:
-   "How do I query directo items/customers/receipts?"
-   "How do I add a new directo  endpoint?"
-   "How do I validate directo XML schemas?"
-   "Why is my date filter not working?"
-   "How do I configure the directo client?"
-   "How do I build Directo put() or putBatch() XML?"
-   "Why does schema validation fail for my customer or item payload?"

## Core Concepts

The SDK revolves around a single `Client` instance that provides access to various `Endpoint` classes.

### Initialization

```php
use Directo\Client;
use Directo\Config;

$config = new Config(
    token: 'your-api-token',
    // Optional configuration
    baseUrl: 'https://login.directo.ee/...',
    validateSchema: false,
);

$client = new Client($config);
```

### Basic Query Patterns

**Listing Items:**
```php
// Simple list
$items = $client->items()->list();

// Filtered list
$items = $client->items()->list([
    'class' => 'ELECTRONICS',
    'ts' => '12.01.2026', // Timestamp filter
]);
```

**Listing Customers:**
```php
$customers = $client->customers()->list([
    'code' => 'CUST*', // Wildcard usually supported by Directo
]);
```

### PUT Payload Rules

`put()` and `putBatch()` must follow the Directo IN schema, not the OUT field names returned by `list()`.

-   Use English IN schema names like `code`, `name`, `email`, `class`, and `salesprice`.
-   Put those fields inside `@attributes` on the record node.
-   Treat parsed `list()` output such as `@code` and `@name` as response fields, not request keys.

**Example: Writing a Customer**
```php
$client->customers()->put([
    '@attributes' => [
        'code' => 'CUST001',
        'name' => 'Acme OU',
        'email' => 'info@example.com',
    ],
]);
```

## Advanced Topics

For detailed guides, refer to the following resources:

-   **[Endpoints Reference](references/endpoints.md)**: Detailed guide on available endpoints (Items, Customers, Receipts) and their specific filters/fields.
-   **[Configuration & Validation](references/configuration.md)**: Deep dive into `Config` options, schema validation, and error handling.
-   **[Extending the SDK](references/extending.md)**: Step-by-step guide to creating new `AbstractEndpoint` implementations for custom Directo modules.
-   **[XML Structure](references/xml_structure.md)**: Detailed guide to the SDK's PUT/list XML mapping rules and `@attributes` usage.
