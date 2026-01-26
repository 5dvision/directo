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
    'kood' => 'CUST001',        // Required: Customer code (key)
    'nimi' => 'Test Customer',  // Customer name
    'email' => 'test@test.com', // Email
    'telefon' => '+372555',     // Phone
]);
```

### Batch Operations

Create or update multiple customers:

```php
$result = $client->customers()->putBatch([
    ['kood' => 'CUST001', 'nimi' => 'Customer 1'],
    ['kood' => 'CUST002', 'nimi' => 'Customer 2'],
]);
```

## Response Fields

The API returns these fields (among others, depending on Directo configuration):

| Field | Description |
|-------|-------------|
| `kood` | Customer code |
| `nimi` | Customer name |
| `email` | Email address |
| `telefon` | Phone number |
| `registrikood` | Registration number |
| `aadress` | Address |
| `linn` | City |
| `postiindex` | Postal code |
| `riik` | Country code |
| `kliendiryhm` | Customer group |
| `hinnaklass` | Price class |
| `suletud` | Closed flag (0/1) |

## XML Structure

### Input (PUT)

```xml
<kliendid>
  <klient kood="CUST001">
    <nimi>Customer Name</nimi>
    <email>test@test.com</email>
  </klient>
</kliendid>
```

### Output (GET)

```xml
<results>
  <customer>
    <kood>CUST001</kood>
    <nimi>Customer Name</nimi>
    <email>test@test.com</email>
  </customer>
</results>
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
