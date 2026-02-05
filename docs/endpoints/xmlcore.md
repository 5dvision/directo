# Custom XMLCore Requests

The `xmlCore()` endpoint allows you to send custom requests to Directo for data types or company-specific endpoints that are not yet natively supported by the SDK.

It provides full access to the Directo API while retaining SDK benefits like:
- **Authentication**: Token handling is automatic.
- **Error Handling**: API errors are detected and thrown as exceptions.
- **Response Parsing**: XML responses are automatically converted to PHP arrays.
- **Validation**: Optional XSD schema validation.

> [!NOTE]
> For official Directo XMLCore documentation, see: [https://wiki.directo.ee/et/xmlcore](https://wiki.directo.ee/et/xmlcore)

## Usage Options

There are three ways to send data, depending on your input format.

### 1. Using `putArray()` (Recommended for Full Arrays)

Use this when you have the **complete** array structure (including root elements) and want the SDK to handle the XML conversion. This is the most flexible option.

```php
// Your data structure matches the XML structure
$data = [
    'invoices' => [
        'invoice' => [
            '@attributes' => ['id' => 123],
            'rows' => [
                'row' => [
                    ['item' => 'A1', 'qty' => 1],
                    ['item' => 'B2', 'qty' => 5],
                ]
            ]
        ]
    ]
];

// Configure validation schema (optional)
$endpoint = $client->xmlCore('invoice')->setSchemas(['put' => 'invoice.xsd']);

// Send request
$response = $endpoint->putArray($data);
```

### 2. Using `put()` (Strict Structure)

Use this when you want the SDK to enforce the XML wrapping tags (root and record elements). You pass only the **inner** data row(s).

**Configuration is required** for this method.

```php
$client->xmlCore('invoice')
    ->setXmlElements([
        'root' => 'invoices',   // Outer wrapper
        'record' => 'invoice',  // Per-record wrapper
    ])
    ->put([
        ['code' => 'INV001', 'total' => 100],
        ['code' => 'INV002', 'total' => 200],
    ]);
```

### 3. Using `putRaw()` (Pre-built XML)

Use this when you already have the XML string (e.g., from a legacy system or external builder).

```php
$xml = '<invoices><invoice code="INV001"><total>100</total></invoice></invoices>';

$client->xmlCore('invoice')->putRaw($xml);
```

## Schema Validation

You can validate your custom requests against a local XSD file before sending them to Directo. This helps catch structure errors early.

```php
$client->xmlCore('invoice')
    ->setSchemas(['put' => __DIR__ . '/schemas/my_invoice.xsd'])
    ->putArray($data);
```

If the XML structure doesn't match the schema, a `SchemaValidationException` will be thrown with detailed errors.
