# Items Endpoint

The Items endpoint provides access to item/product records in Directo.

**API Parameter:** `what=item`

> 📚 **Directo Documentation:**
> - [Items API (OUT)](https://wiki.directo.ee/et/xml_direct#artiklid_items) - Reading items
> - [Items API (IN)](https://wiki.directo.ee/et/xml_direct#artiklid) - Writing items

## Listing Items

```php
$items = $client->items()->list();
```

### Available Filters

| Filter | Description | Example |
|--------|-------------|---------|
| `class` | Item class/category | `'ELECTRONICS'` |
| `code` | Item code (exact match) | `'ITEM001'` |
| `type` | Item type | `'PRODUCT'` |
| `barcode` | Barcode/EAN | `'4751234'` |
| `supplier` | Supplier code | `'SUPP001'` |
| `supplieritem` | Supplier's item code | `'SP-123'` |
| `closed` | Include closed items | `0` or `1` |
| `ts` | Timestamp for incremental sync | `'2024-01-01'` |

### Example with Filters

```php
$items = $client->items()->list([
    'class' => 'ELECTRONICS',
    'closed' => 0,
]);
```

## Creating/Updating Items

Use the `put()` method to create or update an item (upsert):

```php
$result = $client->items()->put([
    'kood' => 'ITEM001',        // Required: Item code (key)
    'nimetus' => 'New Product', // Item name
    'klass' => 'ELECTRONICS',   // Item class
    'hind' => 99.99,            // Price
]);
```

### Batch Operations

Create or update multiple items:

```php
$result = $client->items()->putBatch([
    ['kood' => 'ITEM001', 'nimetus' => 'Product 1', 'hind' => 10.00],
    ['kood' => 'ITEM002', 'nimetus' => 'Product 2', 'hind' => 20.00],
    ['kood' => 'ITEM003', 'nimetus' => 'Product 3', 'hind' => 30.00],
]);
```

## Response Fields

The API returns these fields (among others, depending on Directo configuration):

| Field | Description |
|-------|-------------|
| `kood` | Item code |
| `nimetus` | Item name |
| `nimetus2` | Alternative name |
| `klass` | Item class |
| `yksus` | Unit of measure |
| `ribakood` | Barcode |
| `hind` | Price |
| `kaal` | Weight |
| `maht` | Volume |
| `tarnija` | Supplier code |
| `tarnija_artikkel` | Supplier's item code |
| `suletud` | Closed flag (0/1) |

## XML Structure

### Input (PUT)

```xml
<artiklid>
  <artikkel kood="ITEM001">
    <nimetus>Product Name</nimetus>
    <klass>ELECTRONICS</klass>
    <hind>99.99</hind>
  </artikkel>
</artiklid>
```

### Output (GET)

```xml
<results>
  <item>
    <kood>ITEM001</kood>
    <nimetus>Product Name</nimetus>
    <klass>ELECTRONICS</klass>
    <hind>99.99</hind>
  </item>
</results>
```

## Schema Files

| Type | File | URL |
|------|------|-----|
| Output | `ws_artiklid.xsd` | `https://login.directo.ee/xmlcore/cap_xml_direct/ws_artiklid.xsd` |
| Input | `xml_IN_artiklid.xsd` | `https://login.directo.ee/xmlcore/cap_xml_direct/xml_IN_artiklid.xsd` |

## See Also

- [Customers Endpoint](customers.md)
- [Schema Validation](../schema-validation.md)
- [Error Handling](../error-handling.md)
