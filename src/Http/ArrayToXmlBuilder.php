<?php

declare(strict_types=1);

namespace Directo\Http;

use DOMDocument;
use SimpleXMLElement;

/**
 * General-purpose array-to-XML converter.
 */
final class ArrayToXmlBuilder
{
    /**
     * Convert an array to an XML string.
     *
     * @param array<string, mixed> $data The array to convert
     * @param string|null $rootElement The name of the root element (default: 'root')
     * @param string $version The XML version (default: '1.0')
     * @param string $encoding The XML encoding (default: 'utf-8')
     * @param bool $pretty Whether to format the output with indentation and newlines (default: false)
     *
     * @return string The generated XML string
     */
    public static function arrayToXml(
        array $data,
        ?string $rootElement = null,
        string $version = '1.0',
        string $encoding = 'utf-8',
        bool $pretty = false
    ): string {
        $xml = new SimpleXMLElement(
            sprintf(
                '<?xml version="%s" encoding="%s"?><%s/>',
                $version,
                $encoding,
                $rootElement ?? 'root'
            )
        );

        self::arrayToXmlRecursive($data, $xml);

        $xmlString = $xml->asXML();

        if ($pretty) {
            $xmlString = self::prettyPrint($xmlString);
        }

        if ($rootElement !== null) {
            return $xmlString;
        }

        return preg_replace(
            '/<\?xml[^>]+>\s*<root>(.*)<\/root>/s',
            sprintf('<?xml version="%s" encoding="%s"?>$1', $version, $encoding),
            $xmlString
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function arrayToXmlRecursive(array $data, SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            if ($key === '@attributes') {
                foreach ($value as $attrKey => $attrValue) {
                    $xml->addAttribute($attrKey, self::sanitizeValue($attrValue));
                }

                continue;
            }

            if ($key === '@cdata') {
                $node = dom_import_simplexml($xml);
                $doc = $node->ownerDocument;
                $node->appendChild($doc->createCDATASection((string) $value));
                continue;
            }

            if ($key === '@value') {
                $xml->{0} = self::sanitizeValue($value);
                continue;
            }

            if (is_array($value)) {
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
                    $subnode = $xml->addChild($key);
                    self::arrayToXmlRecursive($value, $subnode);
                }
            } else {
                $xml->addChild($key, self::sanitizeValue($value));
            }
        }
    }

    /**
     * Sanitize a value for XML output.
     *
     * @param mixed $value The value to sanitize
     *
     * @return string The sanitized value
     */
    private static function sanitizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format an XML string with indentation and newlines.
     *
     * @param string $xml The XML string to format
     *
     * @return string The formatted XML string
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
     * Validate XML against an XSD schema.
     *
     * @param string $xml The XML string to validate
     * @param string $xsdPath The path to the XSD schema file
     *
     * @return bool True if validation passes
     *
     * @throws \Directo\Exception\SchemaValidationException If validation fails
     * @throws \InvalidArgumentException If schema file is not found
     */
    public static function validateXml(string $xml, string $xsdPath): bool
    {
        $validator = new \Directo\Schema\SchemaValidator();
        $validator->validate($xml, $xsdPath);

        return true;
    }
}
