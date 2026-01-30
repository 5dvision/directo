<?php

declare(strict_types=1);

namespace Directo\Http;

use DOMDocument;
use Exception;
use SimpleXMLElement;

/**
 * General-purpose array-to-XML converter.
 */
final class ArrayToXmlBuilder
{
    /**
     * @param array<string, mixed> $data
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

        $xml = new SimpleXMLElement(
            sprintf('<?xml version="%s" encoding="%s"?><root/>', $version, $encoding)
        );
        self::arrayToXmlRecursive($data, $xml);

        $xmlString = $xml->asXML();

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

    private static function sanitizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function prettyPrint(string $xml): string
    {
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);

        return $dom->saveXML();
    }

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
            $errorMessages = array_map(fn(\LibXMLError $error): string => sprintf('Line %d: %s', $error->line, trim($error->message)), $errors);

            libxml_clear_errors();

            throw new Exception("XML validation failed:\n".implode("\n", $errorMessages));
        }

        libxml_clear_errors();

        return true;
    }
}
