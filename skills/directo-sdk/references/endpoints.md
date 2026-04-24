# Directo SDK Endpoints

This document details the available `Client` methods and `Endpoint` classes for querying and updating Directo entities.

## Items

The `items()` endpoint manages products and inventory.

### Filters

-   `class`: Product class/group
-   `code`: Product code
-   `type`: Item type
-   `barcode`: Barcode/EAN
-   `supplier`: Supplier code
-   `supplieritem`: Supplier item code
-   `closed`: Include closed items (`0` or `1`)
-   `ts`: Timestamp (YYYY-MM-DD HH:mm:ss) for modification filtering

### Examples

```php
// List items modified since yesterday
$items = $client->items()->list([
    'ts' => date('Y-m-d H:i:s', strtotime('-1 day')),
]);

// Find item by code
$items = $client->items()->list(['code' => 'PROD-123']);

// Create or update an item
$client->items()->put([
    '@attributes' => [
        'code' => 'PROD-123',
        'name' => 'Demo Product',
        'class' => 'ELECTRONICS',
    ],
]);
```

## Customers

The `customers()` endpoint manages customer accounts.

### Filters

-   `code`: Customer code
-   `loyaltycard`: Loyalty card number
-   `regno`: Registration number
-   `email`: Email address
-   `phone`: Phone number
-   `closed`: Include closed customers (`0` or `1`)
-   `ts`: Modification timestamp

### Examples

```php
// List all customers
$customers = $client->customers()->list();

// Create or update a customer
$client->customers()->put([
    '@attributes' => [
        'code' => 'CUST001',
        'name' => 'Acme OU',
        'email' => 'info@example.com',
    ],
]);
```

## Receipts

The `receipts()` endpoint retrieves payment receipts.

### Filters

-   `number`: Receipt number
-   `date1`: Start date
-   `date2`: End date
-   `ts`: Modification timestamp

### Examples

```php
// List receipts for current month
$receipts = $client->receipts()->list([
    'date1' => date('Y-m-01'),
    'date2' => date('Y-m-t'),
]);
```

## XMLCore (Generic)

The `xmlCore()` endpoint allows executing arbitrary Directo XML commands not covered by dedicated endpoints.

```php
// Execute a custom XML command
$response = $client->xmlCore()->putRaw('<root><command>...</command></root>');
```
