# Configuration & Schema Validation

## Configuration

The `Directo\Config` class accepts the following parameters:

```php
$config = new Config(
    // Required
    token: 'your-api-token',

    // Optional
    baseUrl: 'https://login.directo.ee/...', // Default: Directo main URL
    tokenParamName: 'token',                 // Default: 'token', sometimes 'key'
    timeout: 30.0,                           // Request timeout in seconds
    connectTimeout: 10.0,                    // Connection timeout
    validateSchema: false,                   // Enable XSD validation (see below)
    treatEmptyAsNull: true,                  // Convert empty strings to null
);
```

## Schema Validation

The SDK can validate XML requests and responses against Directo's official XSD schemas. This is useful for debugging and preventing malformed data.

### Enabling Validation

Set `validateSchema: true` in your `Config`.

```php
$config = new Config(
    token: '...',
    validateSchema: true,
);
```

### Managing Schemas

Schemas are downloaded from Directo servers. You must download them before validation works.

**Command:**
```bash
composer schemas:update
```

This downloads XSDs to `resources/xsd/`. These files should generally be committed to your repository if you rely on them for consistent validation in CI pipelines.

### Handling Errors

When validation fails, a `Directo\Exception\SchemaValidationException` is thrown.

```php
try {
    $client->items()->put([
        '@attributes' => [
            'code' => 'ITEM001',
            'name' => 'Demo Product',
            'class' => 'SERVICES',
        ],
    ]);
} catch (SchemaValidationException $e) {
    // Get structured validation errors
    $errors = $e->getFormattedErrors();
    foreach ($errors as $error) {
        error_log($error);
    }
}
```

For `put()` validation, remember that Directo IN schemas use attribute-based payloads. Passing OUT fields such as `kood`, `nimi`, or `hind` as request keys will usually fail validation even though those names may appear in older Directo examples or legacy integrations.
