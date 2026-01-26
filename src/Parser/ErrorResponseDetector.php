<?php

declare(strict_types=1);

namespace Directo\Parser;

use Directo\Exception\ApiErrorException;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Detects and extracts Directo-specific error responses.
 *
 * Directo XMLCore API may return errors in various formats:
 *
 * 1. Root <error> element:
 *    <error>Error message</error>
 *
 * 2. Error inside <results>:
 *    <results>
 *      <error>Error message</error>
 *    </results>
 *
 * 3. Multiple <error> elements:
 *    <results>
 *      <errors>
 *        <error>First error</error>
 *        <error>Second error</error>
 *      </errors>
 *    </results>
 *
 * 4. Error as attribute:
 *    <results error="1" message="Error message"/>
 *
 * 5. Status with error code:
 *    <results>
 *      <status code="error">Error description</status>
 *    </results>
 *
 * 6. Auth error - invalid/missing token:
 *    <result type="5" desc="Unauthorized"/>
 *
 * 7. Auth error - token parameter missing:
 *    <err>token required</err>
 *
 * This class detects these patterns and throws ApiErrorException
 * with all extracted error messages.
 *
 * Design notes:
 * - Called before parsing to detect errors early
 * - Extracts all errors, not just the first
 * - Provides raw XML for debugging
 */
final class ErrorResponseDetector
{
    /**
     * Known error element names (case-insensitive search).
     *
     * @var array<int, string>
     */
    private const ERROR_ELEMENTS = [
        'error',
        'errors',
        'err',       // Directo auth error: <err>token required</err>
        'viga',      // Estonian: "error"
        'veateade',  // Estonian: "error message"
    ];

    /**
     * Result type codes that indicate errors.
     * Directo returns: <result type="5" desc="Unauthorized"/>
     *
     * @var array<int, string>
     */
    private const ERROR_RESULT_TYPES = [
        '5',         // Unauthorized
        'error',
        'err',
    ];

    /**
     * Known error attribute names.
     *
     * @var array<int, string>
     */
    private const ERROR_ATTRIBUTES = [
        'error',
        'errormessage',
        'error_message',
        'viga',
    ];

    /**
     * Status codes that indicate errors.
     *
     * @var array<int, string>
     */
    private const ERROR_STATUS_CODES = [
        'error',
        'err',
        'fail',
        'failed',
        'viga',
    ];

    /**
     * Check if response contains errors and throw if so.
     *
     * @param  string  $xml  Raw XML response
     * @param  array<string, mixed>  $context  Context for error reporting
     *
     * @throws ApiErrorException If error response detected
     */
    public function detectAndThrow(string $xml, array $context = []): void
    {
        if (trim($xml) === '') {
            return;
        }

        // Quick string check before parsing (performance optimization)
        if (! $this->mayContainError($xml)) {
            return;
        }

        $errors = $this->extractErrors($xml);

        if (! empty($errors)) {
            throw new ApiErrorException(
                $this->buildMessage($errors),
                $errors,
                $xml,
                $context,
            );
        }
    }

    /**
     * Quick check if XML may contain error elements.
     *
     * Uses string matching for performance - avoids DOM parsing
     * when response clearly doesn't contain errors.
     */
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

        // Check for status elements
        if (str_contains($xmlLower, '<status')) {
            return true;
        }

        // Check for result elements with type attribute (auth errors)
        if (str_contains($xmlLower, '<result')) {
            return true;
        }

        return false;
    }

    /**
     * Extract all error messages from XML.
     *
     * @return array<int, string> Extracted error messages
     */
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

            // Check root element
            $root = $dom->documentElement;
            if ($root === null) {
                return [];
            }

            // Strategy 1: Root is an error element
            if ($this->isErrorElement($root)) {
                // Handle <errors> container specially - extract children
                if (strtolower($root->localName) === 'errors') {
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

            // Strategy 2: Check for error attributes on root
            $attrErrors = $this->extractAttributeErrors($root);
            if (! empty($attrErrors)) {
                return $attrErrors;
            }

            // Strategy 3: Use XPath to find error elements anywhere
            $xpath = new DOMXPath($dom);
            $errors = array_merge($errors, $this->extractXPathErrors($xpath));

            // Strategy 4: Check for status elements with error codes
            $errors = array_merge($errors, $this->extractStatusErrors($xpath));

            // Strategy 5: Check for <result type="5"> error pattern
            $errors = array_merge($errors, $this->extractResultErrors($xpath));

            return array_values(array_unique(array_filter($errors)));
        } finally {
            libxml_use_internal_errors($previousUseErrors);
        }
    }

    /**
     * Check if element is an error element by name.
     */
    private function isErrorElement(DOMElement $element): bool
    {
        $nameLower = strtolower($element->localName);

        return in_array($nameLower, self::ERROR_ELEMENTS, true);
    }

    /**
     * Extract errors from element attributes.
     *
     * @return array<int, string>
     */
    private function extractAttributeErrors(DOMElement $element): array
    {
        $errors = [];

        // Check error="1" or error="true" with message attribute
        foreach (self::ERROR_ATTRIBUTES as $attr) {
            if ($element->hasAttribute($attr)) {
                $value = $element->getAttribute($attr);

                // error="1" or error="true" - look for message elsewhere
                if (in_array($value, ['1', 'true', 'yes'], true)) {
                    // Look for message attribute
                    foreach (['message', 'errormessage', 'error_message', 'msg', 'teade'] as $msgAttr) {
                        if ($element->hasAttribute($msgAttr)) {
                            $errors[] = trim($element->getAttribute($msgAttr));
                        }
                    }

                    // If no message attribute, use text content
                    if (empty($errors) && trim($element->textContent) !== '') {
                        $errors[] = trim($element->textContent);
                    }

                    // If still no message, use generic
                    if (empty($errors)) {
                        $errors[] = 'An error occurred';
                    }
                } else {
                    // The attribute value itself is the error message
                    $errors[] = trim($value);
                }
            }
        }

        return array_filter($errors);
    }

    /**
     * Extract errors using XPath queries.
     *
     * @return array<int, string>
     */
    private function extractXPathErrors(DOMXPath $xpath): array
    {
        $errors = [];
        $queries = [];

        // Build case-insensitive queries for error elements
        foreach (self::ERROR_ELEMENTS as $element) {
            // Direct children
            $queries[] = "//*[translate(local-name(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = '{$element}']";
        }

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    if ($node instanceof DOMElement) {
                        // For <errors> container, get child <error> elements
                        if (strtolower($node->localName) === 'errors') {
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

    /**
     * Get error text from an element.
     *
     * Checks textContent first, then common message attributes.
     */
    private function getErrorText(DOMElement $element): string
    {
        // First try text content
        $text = trim($element->textContent);
        if ($text !== '') {
            return $text;
        }

        // Check common message attributes: desc, message, msg
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

    /**
     * Extract errors from status elements with error codes.
     *
     * @return array<int, string>
     */
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
                        if ($text !== '') {
                            $errors[] = $text;
                        } else {
                            $errors[] = sprintf('Status code: %s', $code);
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Extract errors from result elements with error types.
     *
     * Detects patterns like: <result type="5" desc="Unauthorized"/>
     *
     * @return array<int, string>
     */
    private function extractResultErrors(DOMXPath $xpath): array
    {
        $errors = [];

        $nodes = $xpath->query('//result[@type] | //Result[@type] | //RESULT[@type]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $type = strtolower($node->getAttribute('type'));
                    if (in_array($type, self::ERROR_RESULT_TYPES, true)) {
                        // Try desc attribute first
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

    /**
     * Build primary error message from extracted errors.
     */
    private function buildMessage(array $errors): string
    {
        if (count($errors) === 0) {
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
