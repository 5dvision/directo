<?php

declare(strict_types=1);

namespace Directo\Http;

use DOMDocument;
use DOMElement;
use Stringable;

/**
 * Builds XML request bodies from PHP arrays.
 */
final class RequestBuilder
{
    private const ATTRIBUTES_KEY = '@attributes';

    /**
     * @param array<string, mixed> $data
     */
    public function build(
        string $rootElement,
        string $recordElement,
        array $data,
        ?string $keyAttribute = null,
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement($rootElement);
        $dom->appendChild($root);

        $record = $dom->createElement($recordElement);

        if ($keyAttribute !== null && isset($data[$keyAttribute])) {
            $record->setAttribute($keyAttribute, (string) $data[$keyAttribute]);
            unset($data[$keyAttribute]);
        }

        $this->addElements($dom, $record, $data);
        $root->appendChild($record);

        $xml = $dom->saveXML();

        return $xml !== false ? $xml : '';
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    public function buildBatch(
        string $rootElement,
        string $recordElement,
        array $records,
        ?string $keyAttribute = null,
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement($rootElement);
        $dom->appendChild($root);

        foreach ($records as $data) {
            $record = $dom->createElement($recordElement);

            if ($keyAttribute !== null && isset($data[$keyAttribute])) {
                $record->setAttribute($keyAttribute, (string) $data[$keyAttribute]);
                unset($data[$keyAttribute]);
            }

            $this->addElements($dom, $record, $data);
            $root->appendChild($record);
        }

        $xml = $dom->saveXML();

        return $xml !== false ? $xml : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function addElements(DOMDocument $dom, DOMElement $parent, array $data): void
    {
        foreach ($data as $key => $value) {
            if ($key === self::ATTRIBUTES_KEY) {
                if (is_array($value)) {
                    foreach ($value as $attrName => $attrValue) {
                        $parent->setAttribute((string) $attrName, (string) $attrValue);
                    }
                }

                continue;
            }

            if (is_array($value)) {
                if ($this->isNumericArray($value)) {
                    foreach ($value as $item) {
                        $child = $dom->createElement($key);
                        if (is_array($item)) {
                            $this->addElements($dom, $child, $item);
                        } else {
                            $child->textContent = $this->formatValue($item);
                        }

                        $parent->appendChild($child);
                    }
                } else {
                    $child = $dom->createElement($key);
                    $this->addElements($dom, $child, $value);
                    $parent->appendChild($child);
                }
            } else {
                $child = $dom->createElement($key);
                $child->textContent = $this->formatValue($value);
                $parent->appendChild($child);
            }
        }
    }

    private function isNumericArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    private function formatValue(mixed $value): string
    {
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
