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
     * @return array<int, array<string, mixed>>
     */
    private function extractRecords(DOMElement $root): array
    {
        $records = [];

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

        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $name = $child->nodeName;
            $value = $this->extractValue($child);

            // Handle repeated elements (create array)
            if (isset($result[$name])) {
                if (! is_array($result[$name]) || ! isset($result[$name][0])) {
                    $result[$name] = [$result[$name]];
                }
                $result[$name][] = $value;
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
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

        if ($hasChildElements) {
            return $this->elementToArray($element);
        }

        // Leaf node: return text content
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
