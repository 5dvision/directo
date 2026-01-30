<?php

declare(strict_types=1);

namespace Directo\Http;

use Directo\Exception\ApiErrorException;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Detects and extracts Directo-specific error responses.
 */
final class ErrorResponseDetector
{
    private const ERROR_ELEMENTS = [
        'error',
        'errors',
        'err',
        'viga',
        'veateade',
    ];

    private const ERROR_RESULT_TYPES = [
        '5',
        'error',
        'err',
    ];

    private const ERROR_ATTRIBUTES = [
        'error',
        'errormessage',
        'error_message',
        'viga',
    ];

    private const ERROR_STATUS_CODES = [
        'error',
        'err',
        'fail',
        'failed',
        'viga',
    ];

    public function detectAndThrow(string $xml, array $context = []): void
    {
        if (trim($xml) === '') {
            return;
        }

        if (! $this->mayContainError($xml)) {
            return;
        }

        $errors = $this->extractErrors($xml);

        if ($errors !== []) {
            throw new ApiErrorException(
                $this->buildMessage($errors),
                $errors,
                $xml,
                $context,
            );
        }
    }

    private function mayContainError(string $xml): bool
    {
        $xmlLower = strtolower($xml);

        foreach (self::ERROR_ELEMENTS as $element) {
            if (str_contains($xmlLower, '<'.$element)) {
                return true;
            }
        }

        foreach (self::ERROR_ATTRIBUTES as $attr) {
            if (str_contains($xmlLower, $attr.'=')) {
                return true;
            }
        }

        if (str_contains($xmlLower, '<status')) {
            return true;
        }

        return str_contains($xmlLower, '<result');
    }

    private function extractErrors(string $xml): array
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            $loaded = $dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NONET);

            if (! $loaded) {
                libxml_clear_errors();
                return [];
            }

            $errors = [];
            $root = $dom->documentElement;
            if (!$root instanceof \DOMElement) {
                return [];
            }

            if ($this->isErrorElement($root)) {
                if (strtolower((string) $root->localName) === 'errors') {
                    foreach ($root->childNodes as $child) {
                        if ($child instanceof DOMElement) {
                            $text = trim($child->textContent);
                            if ($text !== '') {
                                $errors[] = $text;
                            }
                        }
                    }

                    return array_values(array_unique(array_filter($errors)));
                }

                $errors[] = trim($root->textContent);
                return array_filter($errors);
            }

            $attrErrors = $this->extractAttributeErrors($root);
            if ($attrErrors !== []) {
                return $attrErrors;
            }

            $xpath = new DOMXPath($dom);
            $errors = array_merge($errors, $this->extractXPathErrors($xpath));
            $errors = array_merge($errors, $this->extractStatusErrors($xpath));
            $errors = array_merge($errors, $this->extractResultErrors($xpath));

            return array_values(array_unique(array_filter($errors)));
        } finally {
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    private function isErrorElement(DOMElement $element): bool
    {
        $nameLower = strtolower((string) $element->localName);
        return in_array($nameLower, self::ERROR_ELEMENTS, true);
    }

    private function extractAttributeErrors(DOMElement $element): array
    {
        $errors = [];

        foreach (self::ERROR_ATTRIBUTES as $attr) {
            if ($element->hasAttribute($attr)) {
                $value = $element->getAttribute($attr);

                if (in_array($value, ['1', 'true', 'yes'], true)) {
                    foreach (['message', 'errormessage', 'error_message', 'msg', 'teade'] as $msgAttr) {
                        if ($element->hasAttribute($msgAttr)) {
                            $errors[] = trim($element->getAttribute($msgAttr));
                        }
                    }

                    if ($errors === [] && trim($element->textContent) !== '') {
                        $errors[] = trim($element->textContent);
                    }

                    if ($errors === []) {
                        $errors[] = 'An error occurred';
                    }
                } else {
                    $errors[] = trim($value);
                }
            }
        }

        return array_filter($errors);
    }

    private function extractXPathErrors(DOMXPath $xpath): array
    {
        $errors = [];
        $queries = [];

        foreach (self::ERROR_ELEMENTS as $element) {
            $queries[] = sprintf("//*[translate(local-name(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = '%s']", $element);
        }

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    if ($node instanceof DOMElement) {
                        if (strtolower((string) $node->localName) === 'errors') {
                            foreach ($node->childNodes as $child) {
                                if ($child instanceof DOMElement) {
                                    $text = $this->getErrorText($child);
                                    if ($text !== '') {
                                        $errors[] = $text;
                                    }
                                }
                            }
                        } else {
                            $text = $this->getErrorText($node);
                            if ($text !== '') {
                                $errors[] = $text;
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    private function getErrorText(DOMElement $element): string
    {
        $text = trim($element->textContent);
        if ($text !== '') {
            return $text;
        }

        foreach (['desc', 'description', 'message', 'msg', 'teade', 'kirjeldus'] as $attr) {
            if ($element->hasAttribute($attr)) {
                $attrText = trim($element->getAttribute($attr));
                if ($attrText !== '') {
                    return $attrText;
                }
            }
        }

        return '';
    }

    private function extractStatusErrors(DOMXPath $xpath): array
    {
        $errors = [];
        $nodes = $xpath->query('//status[@code] | //Status[@code] | //STATUS[@code]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $code = strtolower($node->getAttribute('code'));
                    if (in_array($code, self::ERROR_STATUS_CODES, true)) {
                        $text = trim($node->textContent);
                        $errors[] = $text !== '' ? $text : sprintf('Status code: %s', $code);
                    }
                }
            }
        }

        return $errors;
    }

    private function extractResultErrors(DOMXPath $xpath): array
    {
        $errors = [];
        $nodes = $xpath->query('//result[@type] | //Result[@type] | //RESULT[@type]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $type = strtolower($node->getAttribute('type'));
                    if (in_array($type, self::ERROR_RESULT_TYPES, true)) {
                        if ($node->hasAttribute('desc')) {
                            $errors[] = trim($node->getAttribute('desc'));
                        } elseif ($node->hasAttribute('description')) {
                            $errors[] = trim($node->getAttribute('description'));
                        } elseif ($node->hasAttribute('message')) {
                            $errors[] = trim($node->getAttribute('message'));
                        } elseif (trim($node->textContent) !== '') {
                            $errors[] = trim($node->textContent);
                        } else {
                            $errors[] = sprintf('Result type: %s', $type);
                        }
                    }
                }
            }
        }

        return $errors;
    }

    private function buildMessage(array $errors): string
    {
        if ($errors === []) {
            return 'Unknown API error';
        }

        if (count($errors) === 1) {
            return sprintf('Directo API error: %s', $errors[0]);
        }

        return sprintf(
            'Directo API returned %d errors: %s',
            count($errors),
            implode('; ', array_slice($errors, 0, 3)).(count($errors) > 3 ? '...' : ''),
        );
    }
}
