# Directo PHP SDK

Production-ready PHP 8.2+ SDK for the Directo XMLCore API. [Directo XMLCore Documentation](https://wiki.directo.ee/et/xml_direct)

<a href="https://github.com/5dvision/directo/actions"><img src="https://github.com/5dvision/directo/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A modern, type-safe, and testable PHP SDK for integrating with the Directo ERP system via its XMLCore API. It provides a simple fluent interface for managing Customers, Items, Receipts, and more.

## Installation

> **Requires [PHP 8.2+](https://php.net/releases/)**.

⚡️ Get started by requiring the package using [Composer](https://getcomposer.org):

```bash
composer require 5dvision/directo
```

## Usage

```php
<?php

use Directo\Config;
use Directo\Client;

$client = new Client(new Config(
    token: 'your-api-token',
));

// List customers
$customers = $client->customers()->list();

// List items with filters
$items = $client->items()->list([
    'class' => 'ELECTRONICS',
    'closed' => 0,
]);

// Create/update a record
$result = $client->items()->put([
    'kood' => 'ITEM001',
    'nimetus' => 'New Product',
    'hind' => 99.99,
]);
```

## Documentation

| Topic | Description |
|-------|-------------|
| **Endpoints** | |
| [Customers](docs/endpoints/customers.md) | Customer records API (list, put, putBatch) |
| [Items](docs/endpoints/items.md) | Item/product records API (list, put, putBatch) |
| [Receipts](docs/endpoints/receipts.md) | Payment receipt records API (list) |
| **Guides** | |
| [Schema Validation](docs/schema-validation.md) | XSD validation configuration |
| [Error Handling](docs/error-handling.md) | Exception types and handling |
| [Testing](docs/testing.md) | Unit and integration testing |
| [Adding Endpoints](docs/adding-endpoints.md) | Extending the SDK |

## Configuration

```php
use Directo\Config;

$config = new Config(
    token: 'your-api-token',           // Required: API token
    tokenParamName: 'token',            // 'token' or 'key' (default: 'token')
    timeout: 30.0,                      // Request timeout (default: 30s)
    connectTimeout: 10.0,               // Connection timeout (default: 10s)
    validateSchema: false,              // XSD validation (default: false)
    treatEmptyAsNull: true,             // Empty string handling (default: true)
);

$client = new Client($config);
```

## License

MIT License. See [LICENSE](LICENSE) for details.

