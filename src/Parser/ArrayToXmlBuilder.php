<?php

declare(strict_types=1);

namespace Directo\Parser;

use DOMDocument;
use Exception;
use SimpleXMLElement;

/**
 * General-purpose array-to-XML converter with advanced features.
 *
 * Provides flexible XML generation from PHP arrays with support for:
 * - Attributes via '@attributes' key
 * - CDATA sections via '@cdata' key
 * - Text content with attributes via '@value' key
 * - Nested arrays and repeated elements
 * - Pretty printing and XSD validation
 *
 * This is a general-purpose builder. For Directo-specific PUT operations,
 * use XmlRequestBuilder which handles the specific root/record structure.
 *
 * Example usage:
 * ```php
 * $data = [
 *     'item' => [
 *         '@attributes' => ['id' => '123'],
 *         'name' => 'Product',
 *         'prices' => [
 *             ['currency' => 'EUR', '@value' => '100.00'],
 *             ['currency' => 'USD', '@value' => '110.00'],
 *         ]
 *     ]
 * ];
 * $xml = ArrayToXmlBuilder::arrayToXml($data, 'root');
 * ```
 *
 * @see XmlRequestBuilder For Directo-specific request building
 */
final class ArrayToXmlBuilder
{
    /**
     * Convert array to XML string.
     *
     * @param  array<string, mixed>  $data  Data array to convert
     * @param  string|null  $rootElement  Root element name (optional)
     * @param  string  $version  XML version
     * @param  string  $encoding  XML encoding
     * @return string XML string
     */
    public static function arrayToXml(
        array $data,
        ?string $rootElement = null,
        string $version = '1.0',
        string $encoding = 'utf-8'
    ): string {
        if ($rootElement) {
            $xml = new SimpleXMLElement(
                sprintf('<?xml version="%s" encoding="%s"?><%s/>', $version, $encoding, $rootElement)
            );
            self::arrayToXmlRecursive($data, $xml);

            return $xml->asXML();
        }

        // If no root element, wrap in temporary root
        $xml = new SimpleXMLElement(
            sprintf('<?xml version="%s" encoding="%s"?><root/>', $version, $encoding)
        );
        self::arrayToXmlRecursive($data, $xml);

        $xmlString = $xml->asXML();

        // Remove temporary root wrapper
        $xmlString = preg_replace(
            '/<\?xml[^>]+>\s*<root>(.*)<\/root>/s',
            sprintf('<?xml version="%s" encoding="%s"?>$1', $version, $encoding),
            $xmlString
        );

        return $xmlString;
    }

    /**
     * Recursively convert array to XML.
     *
     * @param  array<string, mixed>  $data  Data array
     * @param  SimpleXMLElement  $xml  XML element
     */
    private static function arrayToXmlRecursive(array $data, SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            // Handle attributes
            if ($key === '@attributes') {
                foreach ($value as $attrKey => $attrValue) {
                    $xml->addAttribute($attrKey, self::sanitizeValue($attrValue));
                }

                continue;
            }

            // Handle CDATA
            if ($key === '@cdata') {
                $node = dom_import_simplexml($xml);
                $doc = $node->ownerDocument;
                $node->appendChild($doc->createCDATASection((string) $value));

                continue;
            }

            // Handle value with no children
            if ($key === '@value') {
                $xml->{0} = self::sanitizeValue($value);

                continue;
            }

            // Handle arrays
            if (is_array($value)) {
                // Numeric array - multiple elements with same name
                if (isset($value[0])) {
                    foreach ($value as $item) {
                        $subnode = $xml->addChild($key);
                        if (is_array($item)) {
                            self::arrayToXmlRecursive($item, $subnode);
                        } else {
                            $subnode->{0} = self::sanitizeValue($item);
                        }
                    }
                } else {
                    // Associative array
                    $subnode = $xml->addChild($key);
                    self::arrayToXmlRecursive($value, $subnode);
                }
            } else {
                // Simple value
                $xml->addChild($key, self::sanitizeValue($value));
            }
        }
    }

    /**
     * Sanitize value for XML.
     *
     * @param  mixed  $value  Value to sanitize
     * @return string Sanitized value
     */
    private static function sanitizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // Convert to string
        $value = (string) $value;

        // Replace XML special characters
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Pretty print XML string.
     *
     * @param  string  $xml  XML string
     * @return string Formatted XML string
     */
    public static function prettyPrint(string $xml): string
    {
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);

        return $dom->saveXML();
    }

    /**
     * Validate XML against XSD schema.
     *
     * @param  string  $xml  XML string
     * @param  string  $xsdPath  Path to XSD file
     * @return bool True if valid
     *
     * @throws Exception If validation fails
     */
    public static function validateXsd(string $xml, string $xsdPath): bool
    {
        if (! file_exists($xsdPath)) {
            throw new Exception('XSD file not found: ' . $xsdPath);
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        if (! $dom->schemaValidate($xsdPath)) {
            $errors = libxml_get_errors();
            $errorMessages = array_map(fn (\LibXMLError $error): string => sprintf('Line %d: %s', $error->line, trim($error->message)), $errors);

            libxml_clear_errors();

            throw new Exception("XML validation failed:\n".implode("\n", $errorMessages));
        }

        libxml_clear_errors();

        return true;
    }
}
