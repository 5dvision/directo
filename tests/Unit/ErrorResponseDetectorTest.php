<?php

declare(strict_types=1);

use Directo\Exception\ApiErrorException;
use Directo\Parser\ErrorResponseDetector;

describe('ErrorResponseDetector', function () {
    test('does not throw on empty response', function () {
        $detector = new ErrorResponseDetector();

        $detector->detectAndThrow('', []);
        $detector->detectAndThrow('   ', []);

        expect(true)->toBeTrue(); // No exception
    });

    test('does not throw on valid response without errors', function () {
        $detector = new ErrorResponseDetector();

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<results>
    <customer>
        <code>CUST001</code>
        <name>Test Customer</name>
    </customer>
</results>
XML;

        $detector->detectAndThrow($xml, []);

        expect(true)->toBeTrue();
    });

    test('detects root <error> element', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<error>Invalid API key</error>';

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Directo API error: Invalid API key');

    test('detects <error> inside <results>', function () {
        $detector = new ErrorResponseDetector();

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<results>
    <error>Customer not found</error>
</results>
XML;

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Directo API error: Customer not found');

    test('detects multiple <error> elements', function () {
        $detector = new ErrorResponseDetector();

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<results>
    <errors>
        <error>Field "code" is required</error>
        <error>Field "name" is required</error>
    </errors>
</results>
XML;

        try {
            $detector->detectAndThrow($xml, []);
            expect(false)->toBeTrue(); // Should not reach here
        } catch (ApiErrorException $e) {
            expect($e->getErrors())->toHaveCount(2);
            expect($e->getErrors()[0])->toBe('Field "code" is required');
            expect($e->getErrors()[1])->toBe('Field "name" is required');
            expect($e->hasMultipleErrors())->toBeTrue();
        }
    });

    test('detects error="1" attribute with message', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<results error="1" message="Access denied"/>';

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Access denied');

    test('detects error attribute as message', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<results error="Invalid request format"/>';

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Invalid request format');

    test('detects error element with desc attribute', function () {
        $detector = new ErrorResponseDetector();

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<results>
    <error desc="Invalid item code"/>
</results>
XML;

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Invalid item code');

    test('detects error element with message attribute', function () {
        $detector = new ErrorResponseDetector();

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<results>
    <error message="Something went wrong"/>
</results>
XML;

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Something went wrong');

    test('detects status element with error code', function () {
        $detector = new ErrorResponseDetector();

        $xml = <<<'XML'
<results>
    <status code="error">Operation failed</status>
</results>
XML;

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Operation failed');

    test('detects Estonian error element (viga)', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<viga>Vigane päring</viga>';

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Vigane päring');

    test('case insensitive error detection', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<ERROR>Something went wrong</ERROR>';

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Something went wrong');

    test('preserves context in exception', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<error>Test error</error>';
        $context = ['endpoint' => 'customer', 'filters' => ['code' => 'TEST']];

        try {
            $detector->detectAndThrow($xml, $context);
            expect(false)->toBeTrue();
        } catch (ApiErrorException $e) {
            expect($e->getContext())->toBe($context);
        }
    });

    test('provides raw XML in exception', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<error>Some error message</error>';

        try {
            $detector->detectAndThrow($xml, []);
            expect(false)->toBeTrue();
        } catch (ApiErrorException $e) {
            expect($e->getRawXml())->toBe($xml);
        }
    });

    test('getFormattedErrors returns numbered list', function () {
        $detector = new ErrorResponseDetector();

        $xml = <<<'XML'
<errors>
    <error>First</error>
    <error>Second</error>
</errors>
XML;

        try {
            $detector->detectAndThrow($xml, []);
        } catch (ApiErrorException $e) {
            $formatted = $e->getFormattedErrors();
            expect($formatted[0])->toBe('[1] First');
            expect($formatted[1])->toBe('[2] Second');
        }
    });

    test('handles invalid XML gracefully', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<invalid>not closed';

        // Should not throw - let XmlResponseParser handle invalid XML
        $detector->detectAndThrow($xml, []);

        expect(true)->toBeTrue();
    });

    test('does not false-positive on "error" in content', function () {
        $detector = new ErrorResponseDetector();

        $xml = <<<'XML'
<results>
    <customer>
        <name>Error Handling Corp</name>
        <description>We handle errors professionally</description>
    </customer>
</results>
XML;

        // This should NOT throw because "error" is in text content, not an element
        $detector->detectAndThrow($xml, []);

        expect(true)->toBeTrue();
    });

    test('detects auth error - invalid token (result type=5)', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<result type="5" desc="Unauthorized"/>';

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Unauthorized');

    test('detects auth error - token required', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<err>token required</err>';

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'token required');

    test('detects result type error with text content', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<result type="5">Authentication failed</result>';

        $detector->detectAndThrow($xml, []);
    })->throws(ApiErrorException::class, 'Authentication failed');

    test('auth error exception contains raw XML', function () {
        $detector = new ErrorResponseDetector();

        $xml = '<result type="5" desc="Unauthorized"/>';

        try {
            $detector->detectAndThrow($xml, ['endpoint' => 'customer']);
            expect(false)->toBeTrue();
        } catch (ApiErrorException $e) {
            expect($e->getRawXml())->toBe($xml);
            expect($e->getErrors()[0])->toBe('Unauthorized');
            expect($e->getContext()['endpoint'])->toBe('customer');
        }
    });
});

describe('ApiErrorException', function () {
    test('can be caught as DirectoException', function () {
        $detector = new ErrorResponseDetector();

        try {
            $detector->detectAndThrow('<error>Test</error>', []);
            expect(false)->toBeTrue();
        } catch (\Directo\Exception\DirectoException $e) {
            expect($e)->toBeInstanceOf(ApiErrorException::class);
        }
    });
});
