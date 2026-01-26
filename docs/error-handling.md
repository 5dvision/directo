# Error Handling

The SDK provides comprehensive error handling for various failure scenarios.

## Exception Hierarchy

```
Directo\Exception\DirectoException (base)
├── AuthenticationException    # Invalid token, auth failures
├── ApiErrorException          # Directo API returned an error
├── TransportException         # HTTP/network errors
└── ValidationException        # XSD schema validation failures
```

## AuthenticationException

Thrown when authentication fails:

```php
use Directo\Exception\AuthenticationException;

try {
    $items = $client->items()->list();
} catch (AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage();
    // Check token, request new one if needed
}
```

### Detection Patterns

The SDK detects auth errors from these XML patterns:

```xml
<!-- Pattern 1: result type="5" -->
<results>
  <result type="5">Invalid token</result>
</results>

<!-- Pattern 2: err element -->
<results>
  <err>Authentication failed</err>
</results>
```

## ApiErrorException

Thrown when Directo returns an error response:

```php
use Directo\Exception\ApiErrorException;

try {
    $result = $client->items()->put(['kood' => 'INVALID']);
} catch (ApiErrorException $e) {
    echo "API error: " . $e->getMessage();
}
```

### Detection Patterns

```xml
<!-- Pattern 1: error with desc attribute -->
<error desc="Item not found"/>

<!-- Pattern 2: error with text content -->
<error>Something went wrong</error>

<!-- Pattern 3: message/msg elements -->
<results>
  <message>Operation failed</message>
</results>
```

## TransportException

Thrown for HTTP/network errors:

```php
use Directo\Exception\TransportException;

try {
    $items = $client->items()->list();
} catch (TransportException $e) {
    echo "Network error: " . $e->getMessage();
    echo "HTTP status: " . $e->getCode();
}
```

## ValidationException

Thrown when XSD schema validation fails:

```php
use Directo\Exception\ValidationException;

try {
    $items = $client->items()->list();
} catch (ValidationException $e) {
    echo "Validation failed: " . $e->getMessage();
    
    // Get all validation errors
    foreach ($e->getErrors() as $error) {
        echo "- " . $error . "\n";
    }
}
```

## Catching All Errors

Use the base exception to catch any SDK error:

```php
use Directo\Exception\DirectoException;

try {
    $items = $client->items()->list();
} catch (DirectoException $e) {
    echo "Directo SDK error: " . $e->getMessage();
}
```

## Error Response Detector

The `ErrorResponseDetector` class handles error detection:

```php
use Directo\Parser\ErrorResponseDetector;

$detector = new ErrorResponseDetector();

// Check for auth errors
if ($detector->isAuthError($xml)) {
    throw new AuthenticationException($detector->getAuthErrorMessage($xml));
}

// Check for API errors
if ($detector->isError($xml)) {
    throw new ApiErrorException($detector->getErrorMessage($xml));
}
```

### Helper Methods

```php
// Get error text from desc/description/message/msg attributes
$text = $detector->getErrorText($errorElement);
```

## Logging Errors

The SDK supports PSR-3 logging:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('directo');
$logger->pushHandler(new StreamHandler('directo.log', Logger::DEBUG));

$client = new DirectoClient($token, $logger);
```

## Best Practices

1. **Always catch specific exceptions first**, then general ones
2. **Log errors** for debugging and monitoring
3. **Implement retry logic** for transient network errors
4. **Handle auth errors** by refreshing tokens when possible
5. **Validate input** before sending to reduce API errors

```php
try {
    $result = $client->items()->put($data);
} catch (AuthenticationException $e) {
    // Token expired - refresh and retry
    $client = new DirectoClient($newToken);
    $result = $client->items()->put($data);
} catch (TransportException $e) {
    // Network error - retry with backoff
    sleep(1);
    $result = $client->items()->put($data);
} catch (ApiErrorException $e) {
    // Business logic error - log and notify
    $logger->error('API error', ['message' => $e->getMessage()]);
} catch (DirectoException $e) {
    // Unexpected error
    $logger->critical('Unexpected error', ['exception' => $e]);
    throw $e;
}
```

## See Also

- [Schema Validation](schema-validation.md)
- [PUT Operations](put-operations.md)
- [Testing](testing.md)
