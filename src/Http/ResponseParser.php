<?php

declare(strict_types=1);

namespace Directo\Http;

use Directo\Exception\XmlParseException;
use DOMDocument;
use DOMElement;

/**
 * Generic XML response parser.
 */
final readonly class ResponseParser
{
    public function __construct(
        private bool $treatEmptyAsNull = true,
        private bool $trimStrings = true,
    ) {
    }

    /**
     * Parse XML response into array of records.
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

                throw new XmlParseException('Failed to parse XML response', $errors, $xml, $context);
            }

            $root = $dom->documentElement;
            if (!$root instanceof \DOMElement) {
                return [];
            }

            return $this->extractRecords($root);
        } finally {
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    /**
     * Extract records from root element.
     * @return array<int, array<string, mixed>>
     */
    private function extractRecords(DOMElement $root): array
    {
        $records = [];

        if ($root->nodeName === 'transport') {
            $childElements = [];
            foreach ($root->childNodes as $node) {
                if ($node instanceof DOMElement) {
                    $childElements[] = $node;
                }
            }

            if ($childElements === []) {
                return [];
            }

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
                    foreach ($childElements as $element) {
                        $records[] = $this->elementToArray($element);
                    }

                    return $records;
                }

                $singleElement = $childElements[0];
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
                        foreach ($grandChildren as $recordNode) {
                            $records[] = $this->elementToArray($recordNode);
                        }

                        return $records;
                    }
                }

                return [$this->elementToArray($singleElement)];
            }

            $containerNode = $childElements[0];
            foreach ($containerNode->childNodes as $recordNode) {
                if ($recordNode instanceof DOMElement) {
                    $records[] = $this->elementToArray($recordNode);
                }
            }

            return $records;
        }

        foreach ($root->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $records[] = $this->elementToArray($node);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function elementToArray(DOMElement $element): array
    {
        $result = [];

        foreach ($element->attributes ?? [] as $attr) {
            $result['@'.$attr->nodeName] = $this->normalizeValue($attr->nodeValue);
        }

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

        foreach ($childrenByName as $name => $children) {
            $values = array_map($this->extractValue(...), $children);

            if (count($children) === 1) {
                $parentName = $element->nodeName;
                $shouldBeArray = $this->isPluralContainer($parentName, $name);
                $result[$name] = $shouldBeArray ? $values : $values[0];
            } else {
                $result[$name] = $values;
            }
        }

        return $result;
    }

    private function isPluralContainer(string $parentName, string $childName): bool
    {
        if (str_ends_with($parentName, 's') && !str_ends_with($parentName, 'ss')) {
            $singularized = rtrim($parentName, 's');
            if ($singularized === $childName) {
                return true;
            }

            if (str_ends_with($parentName, 'es')) {
                $singularized = substr($parentName, 0, -2);
                if ($singularized === $childName) {
                    return true;
                }
            }
        }

        return false;
    }

    private function extractValue(DOMElement $element): mixed
    {
        $hasChildElements = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $hasChildElements = true;
                break;
            }
        }

        $hasAttributes = $element->attributes && $element->attributes->length > 0;

        if ($hasChildElements || $hasAttributes) {
            return $this->elementToArray($element);
        }

        return $this->normalizeValue($element->textContent);
    }

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
