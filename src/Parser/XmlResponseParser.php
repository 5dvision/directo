<?php

declare(strict_types=1);

namespace Directo\Parser;

use Directo\Exception\XmlParseException;
use DOMDocument;
use DOMElement;

/**
 * Generic XML response parser.
 *
 * Converts Directo XMLCore responses into associative arrays.
 * Works with any endpoint without endpoint-specific code.
 *
 * Design notes:
 * - Uses DOM (not SimpleXML) for robust error handling
 * - Generic: element names become array keys
 * - Configurable: empty string handling, whitespace trimming
 * - No DTOs: returns array<int, array<string, mixed>>
 *
 * Trade-offs (arrays vs DTOs):
 * Pros:
 * - Zero boilerplate for new endpoints
 * - Easy to extend without code changes
 * - IDE autocomplete via PHPDoc @return annotations
 * - Simple serialization (JSON, etc.)
 *
 * Cons:
 * - No compile-time type safety
 * - Typos in key access won't be caught by IDE
 * - Must document expected keys per endpoint
 *
 * For most SDK use cases, arrays are the pragmatic choice.
 * Consider DTOs only if you need validation/transformation logic
 * tied to specific fields.
 */
final class XmlResponseParser
{
    /**
     * @param  bool  $treatEmptyAsNull  Convert empty strings to null
     * @param  bool  $trimStrings  Trim whitespace from string values
     */
    public function __construct(
        private readonly bool $treatEmptyAsNull = true,
        private readonly bool $trimStrings = true,
    ) {
    }

    /**
     * Parse XML response into array of records.
     *
     * Directo XMLCore returns XML with structure:
     * <results>
     *   <row_name>
     *     <field1>value1</field1>
     *     <field2>value2</field2>
     *   </row_name>
     *   ...
     * </results>
     *
     * This method extracts child elements of the root and converts
     * each to an associative array.
     *
     * @param  string  $xml  Raw XML response
     * @param  array<string, mixed>  $context  Context for error reporting
     * @return array<int, array<string, mixed>> Array of records
     *
     * @throws XmlParseException If XML is invalid
     */
    public function parse(string $xml, array $context = []): array
    {
        if (trim($xml) === '') {
            return [];
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            $loaded = $dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NONET);

            if (! $loaded) {
                $errors = libxml_get_errors();
                libxml_clear_errors();

                throw new XmlParseException(
                    'Failed to parse XML response',
                    $errors,
                    $xml,
                    $context,
                );
            }

            $root = $dom->documentElement;
            if ($root === null) {
                return [];
            }

            return $this->extractRecords($root);
        } finally {
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    /**
     * Extract records from root element.
     *
     * Handles multiple API response formats:
     * - Transport with container (multiple): <transport><customers><customer/><customer/></customers></transport>
     * - Transport without container (multiple): <transport><receipt/><receipt/></transport>
     * - Transport single record (no container): <transport><customer/></transport>
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractRecords(DOMElement $root): array
    {
        $records = [];

        // If root is <transport>, check structure
        if ($root->nodeName === 'transport') {
            // Collect all direct child elements
            $childElements = [];
            foreach ($root->childNodes as $node) {
                if ($node instanceof DOMElement) {
                    $childElements[] = $node;
                }
            }

            if (empty($childElements)) {
                return [];
            }

            // Check if all children have the same name
            $firstChildName = $childElements[0]->nodeName;
            $allSameName = true;
            foreach ($childElements as $element) {
                if ($element->nodeName !== $firstChildName) {
                    $allSameName = false;
                    break;
                }
            }

            if ($allSameName) {
                if (count($childElements) > 1) {
                    // Multiple same-named elements: these ARE the records (e.g., <receipt/><receipt/>)
                    foreach ($childElements as $element) {
                        $records[] = $this->elementToArray($element);
                    }
                    return $records;
                }

                // Single element: check if it looks like a container or a record
                // Container names are typically plural (customers, items, receipts)
                // Record names are singular (customer, item, receipt)
                $singleElement = $childElements[0];
                
                // Check if it has multiple child elements with the same name (indicates container)
                $grandChildren = [];
                foreach ($singleElement->childNodes as $node) {
                    if ($node instanceof DOMElement) {
                        $grandChildren[] = $node;
                    }
                }

                if (count($grandChildren) > 1) {
                    $firstGrandChildName = $grandChildren[0]->nodeName;
                    $allGrandChildrenSameName = true;
                    foreach ($grandChildren as $gc) {
                        if ($gc->nodeName !== $firstGrandChildName) {
                            $allGrandChildrenSameName = false;
                            break;
                        }
                    }

                    if ($allGrandChildrenSameName) {
                        // Has multiple same-named children: this is a container
                        foreach ($grandChildren as $recordNode) {
                            $records[] = $this->elementToArray($recordNode);
                        }
                        return $records;
                    }
                }

                // Single element with no repeated children: this IS the record
                return [$this->elementToArray($singleElement)];
            }

            // Multiple different-named children: treat first as container
            $containerNode = $childElements[0];
            foreach ($containerNode->childNodes as $recordNode) {
                if ($recordNode instanceof DOMElement) {
                    $records[] = $this->elementToArray($recordNode);
                }
            }
            return $records;
        }

        // Default: direct children of root are records (for any other root element name)
        foreach ($root->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $records[] = $this->elementToArray($node);
        }

        return $records;
    }

    /**
     * Convert DOM element to associative array.
     *
     * Handles nested elements recursively.
     *
     * @return array<string, mixed>
     */
    private function elementToArray(DOMElement $element): array
    {
        $result = [];

        // Add attributes as keys prefixed with '@'
        foreach ($element->attributes ?? [] as $attr) {
            $result['@'.$attr->nodeName] = $this->normalizeValue($attr->nodeValue);
        }

        // First pass: collect child elements by name to detect repetition
        $childrenByName = [];
        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            
            $name = $child->nodeName;
            if (!isset($childrenByName[$name])) {
                $childrenByName[$name] = [];
            }
            $childrenByName[$name][] = $child;
        }

        // Second pass: build result with proper array handling
        foreach ($childrenByName as $name => $children) {
            $values = array_map(fn($child) => $this->extractValue($child), $children);
            
            if (count($children) === 1) {
                // Check if parent element name suggests this should be an array (plural container)
                // Common patterns: rows/row, items/item, prices/price, etc.
                $parentName = $element->nodeName;
                $shouldBeArray = $this->isPluralContainer($parentName, $name);
                
                if ($shouldBeArray) {
                    $result[$name] = $values; // Wrap single item in array
                } else {
                    $result[$name] = $values[0]; // Store directly
                }
            } else {
                // Multiple children with same name - always use array
                $result[$name] = $values;
            }
        }

        return $result;
    }

    /**
     * Detect if a parent element is a plural container for child elements.
     * 
     * Returns true if parent name ends with 's' or 'es' and child name is the singular form.
     */
    private function isPluralContainer(string $parentName, string $childName): bool
    {
        // Common plural patterns: rows/row, items/item, prices/price, addresses/address
        if (str_ends_with($parentName, 's') && !str_ends_with($parentName, 'ss')) {
            $singularized = rtrim($parentName, 's');
            if ($singularized === $childName) {
                return true;
            }
            
            // Handle -es ending: addresses/address
            if (str_ends_with($parentName, 'es')) {
                $singularized = substr($parentName, 0, -2);
                if ($singularized === $childName) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Extract value from element.
     *
     * Returns either a scalar value or nested array.
     */
    private function extractValue(DOMElement $element): mixed
    {
        // Check if element has child elements
        $hasChildElements = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $hasChildElements = true;
                break;
            }
        }

        // Check if element has attributes
        $hasAttributes = $element->attributes && $element->attributes->length > 0;

        // If element has children or attributes, convert to array
        if ($hasChildElements || $hasAttributes) {
            return $this->elementToArray($element);
        }

        // Leaf node with no attributes: return text content
        return $this->normalizeValue($element->textContent);
    }

    /**
     * Normalize a scalar value.
     *
     * - Trims whitespace if configured
     * - Converts empty strings to null if configured
     */
    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($this->trimStrings) {
            $value = trim($value);
        }

        if ($this->treatEmptyAsNull && $value === '') {
            return null;
        }

        return $value;
    }
}
