# Receipts Endpoint

The Receipts endpoint provides access to payment receipt records in Directo.

**API Parameter:** `what=receipt`

> 📚 **Directo Documentation:**
> - [Receipts API (OUT)](https://wiki.directo.ee/et/xml_direct#laekumised_receipts) - Reading receipts
> - [Receipts API (IN)](https://wiki.directo.ee/et/xml_direct#laekumised) - Writing receipts

## Listing Receipts

```php
$receipts = $client->receipts()->list();
```

### Available Filters

| Filter | Description | Example |
|--------|-------------|---------|
| `number` | Receipt number (exact match) | `123456` |
| `date1` | Start date filter | `'2024-01-01'` |
| `date2` | End date filter | `'2024-01-31'` |
| `ts` | Timestamp for incremental sync | `'2024-01-01'` |

### Example with Filters

```php
$receipts = $client->receipts()->list([
    'date1' => '2024-01-01',
    'date2' => '2024-01-31',
    'ts' => '2024-01-01',
]);
```

## Response Fields

The API returns these fields (among others, depending on Directo configuration):

> **⚠️ Important**: The Directo API returns receipt data as XML attributes. When accessed through the SDK, attributes are prefixed with `@` (e.g., `@number`, `@confirmed`).

| Field | Description |
|-------|-------------|
| `@number` | Receipt number (attribute) |
| `@confirmed` | Confirmed status (0/1) (attribute) |
| `@ts` | Last modified timestamp (attribute) |
| `rows` | Array of receipt rows (element) |

### Receipt Row Fields

Each receipt contains a `rows` array with the following fields (all are attributes):

| Field | Description |
|-------|-------------|
| `@invoice` | Invoice number |
| `@order` | Order number |
| `@aeg` | Time/date |
| `@customer` | Customer code |
| `@customername` | Customer name |
| `@received` | Received amount |
| `@regno` | Registration number |
| `@invoicesum` | Invoice sum |

## XML Structure

### Output (GET)

```xml
<transport>
  <receipt number="123456" confirmed="1" ts="2024-01-15T10:30:00">
    <rows>
      <row invoice="INV001" customer="CUST001" received="150.00" />
    </rows>
  </receipt>
</transport>
```

## Schema Files

| Type | File | URL |
|------|------|-----|
| Output | `ws_laekumised.xsd` | `https://login.directo.ee/xmlcore/cap_xml_direct/ws_laekumised.xsd` |

## Usage Example

```php
// Get receipts by date range
$receipts = $client->receipts()->list([
    'date1' => '2024-01-01',
    'date2' => '2024-01-31',
]);

foreach ($receipts as $receipt) {
    // Access attributes with @ prefix
    echo "Receipt #{$receipt['@number']}\n";
    echo "Confirmed: {$receipt['@confirmed']}\n";
    
    // Access rows (if present)
    if (isset($receipt['rows']['row'])) {
        $rows = $receipt['rows']['row'];
        // Handle single row vs multiple rows
        if (!isset($rows[0])) {
            $rows = [$rows];
        }
        
        foreach ($rows as $row) {
            echo "  Invoice: {$row['@invoice']}\n";
            echo "  Customer: {$row['@customername']}\n";
            echo "  Amount: {$row['@received']}\n";
        }
    }
}
```

## See Also

- [Customers Endpoint](customers.md)
- [Items Endpoint](items.md)
- [Schema Validation](../schema-validation.md)
- [Error Handling](../error-handling.md)
