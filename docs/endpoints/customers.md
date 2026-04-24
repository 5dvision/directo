# Customers Endpoint

The Customers endpoint provides access to customer records in Directo.

**API Parameter:** `what=customer`

> 📚 **Directo Documentation:**
> - [Customers API (OUT)](https://wiki.directo.ee/et/xml_direct#kliendid_customers) - Reading customers
> - [Customers API (IN)](https://wiki.directo.ee/et/xml_direct#kliendid) - Writing customers

## Listing Customers

```php
$customers = $client->customers()->list();
```

### Available Filters

| Filter | Description | Example |
|--------|-------------|---------|
| `code` | Customer code (exact match) | `'CUST001'` |
| `loyaltycard` | Loyalty card number | `'123456'` |
| `regno` | Registration number | `'12345678'` |
| `email` | Email address | `'test@test.com'` |
| `phone` | Phone number | `'+372555'` |
| `closed` | Include closed customers | `0` or `1` |
| `ts` | Timestamp for incremental sync | `'2024-01-01'` |

### Example with Filters

```php
$customers = $client->customers()->list([
    'code' => 'CUST001',
    'closed' => 0,
]);
```

## Creating/Updating Customers

Use the `put()` method to create or update a customer (upsert):

```php
$result = $client->customers()->put([
    '@attributes' => [
        'code' => 'CUST001',
        'name' => 'Test Customer',
        'email' => 'test@test.com',
        'phone' => '+372555',
        'regno' => '12345678',
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

For customer `put()` requests, the Directo IN schema expects English attribute names on `<customer>` such as `code`, `name`, `email`, and `phone`. Estonian OUT field names like `kood` and `nimi` are not valid request keys for this SDK contract.

### Batch Operations

Create or update multiple customers:

```php
$result = $client->customers()->putBatch([
    [
        '@attributes' => [
            'code' => 'CUST001',
            'name' => 'Customer 1',
        ],
    ],
    [
        '@attributes' => [
            'code' => 'CUST002',
            'name' => 'Customer 2',
        ],
    ],
]);
```

## Response Fields

The parsed response returns XML attributes with an `@` prefix (among others, depending on Directo configuration):

| Field | Description |
|-------|-------------|
| `@code` | Customer code |
| `@name` | Customer name |
| `@email` | Email address |
| `@phone` | Phone number |
| `@regno` | Registration number |
| `@address1` | Address line 1 |
| `@address2` | Address line 2 or city |
| `@country` | Country code |
| `@closed` | Closed flag (0/1) |
| `@ts` | Timestamp |
| `datafields` | Nested custom field container |

## XML Structure

### Input (PUT)

```xml
<customers>
  <customer code="CUST001" name="Customer Name" email="test@test.com" phone="+372555">
    <datafields>
      <data code="vip" content="yes" param="segment"/>
    </datafields>
  </customer>
</customers>
```

### Output (GET)

```xml
<transport>
  <customers>
    <customer code="CUST001" name="Customer Name" email="test@test.com" phone="+372555">
      <datafields/>
    </customer>
  </customers>
</transport>
```

Parsed by the SDK, that record becomes roughly:

```php
[
    '@code' => 'CUST001',
    '@name' => 'Customer Name',
    '@email' => 'test@test.com',
    '@phone' => '+372555',
    'datafields' => [],
]
```

## Schema Files

| Type | File | URL |
|------|------|-----|
| Output | `ws_kliendid.xsd` | `https://login.directo.ee/xmlcore/cap_xml_direct/ws_kliendid.xsd` |
| Input | `xml_IN_kliendid.xsd` | `https://login.directo.ee/xmlcore/cap_xml_direct/xml_IN_kliendid.xsd` |

## See Also

- [Items Endpoint](items.md)
- [Schema Validation](../schema-validation.md)
- [Error Handling](../error-handling.md)
