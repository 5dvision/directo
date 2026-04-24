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
    '@attributes' => [
        'code' => 'ITEM001',
        'name' => 'New Product',
        'class' => 'ELECTRONICS',
        'salesprice' => 99.99,
    ],
]);
```

For item `put()` requests, the Directo IN schema expects English attribute names on `<item>` such as `code`, `name`, `class`, and `salesprice`. Estonian OUT field names like `kood`, `nimetus`, and `hind` are not valid request keys for this SDK contract.

### Batch Operations

Create or update multiple items:

```php
$result = $client->items()->putBatch([
    [
        '@attributes' => [
            'code' => 'ITEM001',
            'name' => 'Product 1',
            'salesprice' => 10.00,
        ],
    ],
    [
        '@attributes' => [
            'code' => 'ITEM002',
            'name' => 'Product 2',
            'salesprice' => 20.00,
        ],
    ],
    [
        '@attributes' => [
            'code' => 'ITEM003',
            'name' => 'Product 3',
            'salesprice' => 30.00,
        ],
    ],
]);
```

## Response Fields

The parsed response returns XML attributes with an `@` prefix (among others, depending on Directo configuration):

| Field | Description |
|-------|-------------|
| `@code` | Item code |
| `@name` | Item name |
| `@class` | Item class |
| `@barcode` | Barcode |
| `@salesprice` | Sales price without VAT |
| `@weight` | Weight |
| `@volume` | Volume |
| `@supplier` | Supplier code |
| `@supplieritem` | Supplier's item code |
| `@closed` | Closed flag (0/1) |
| `datafields` | Nested custom field container |

## XML Structure

### Input (PUT)

```xml
<items>
  <item code="ITEM001" name="Product Name" class="ELECTRONICS" salesprice="99.99">
    <datafields/>
    <packages/>
    <supplieritems/>
    <stocklimits/>
  </item>
</items>
```

### Output (GET)

```xml
<transport>
  <items>
    <item code="ITEM001" name="Product Name" class="ELECTRONICS" salesprice="99.99">
      <datafields/>
    </item>
  </items>
</transport>
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
