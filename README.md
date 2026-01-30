# Directo PHP SDK

Production-ready PHP 8.2+ SDK for the Directo XMLCore API. [Directo XMLCore Documentation](https://wiki.directo.ee/et/xml_direct)

[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## License

MIT License. See [LICENSE](LICENSE) for details.


## Features

- 🚀 **Simple API** - Ergonomic, fluent interface for all endpoints
- 🔒 **Type-safe** - Strict types, PSR-12 compliant, IDE-friendly
- ✅ **Testable** - Full Guzzle MockHandler support
- 📋 **Extensible** - Add new endpoints in 5 minutes
- 🛡️ **Validated** - Optional XSD schema validation
- 🔧 **Configurable** - Flexible auth, timeouts, and response handling


## Requirements

- PHP 8.2+
- ext-dom
- ext-libxml
- guzzlehttp/guzzle ^7.0

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Endpoints](#endpoints)
- [Error Handling](#error-handling)
- [Testing](#testing)

### Documentation

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

## Installation

```bash
composer require 5dvision/directo
```

## Quick Start

```php
<?php

use Directo\Config;
use Directo\DirectoClient;

$client = new DirectoClient(new Config(
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

$client = new DirectoClient($config);
```

## Endpoints

📖 [Customers documentation](docs/endpoints/customers.md)

📖 [Items documentation](docs/endpoints/items.md)

📖 [Error handling documentation](docs/error-handling.md)


## Testing

```php
use Directo\DirectoClient;
use Directo\Transport\MockTransport;

$transport = new MockTransport('<results><item><kood>TEST</kood></item></results>');
$client = DirectoClient::withTransport($transport);

$items = $client->items()->list();
// Returns: [['kood' => 'TEST']]
```

```bash
./vendor/bin/pest
```

📖 [Full documentation](docs/testing.md)

