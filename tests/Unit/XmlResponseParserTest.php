<?php

declare(strict_types=1);

use Directo\Exception\XmlParseException;
use Directo\Parser\XmlResponseParser;

describe('XmlResponseParser', function () {
    test('parses customers XML into array', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('customers.xml'));

        expect($result)->toBeArray();
        expect($result)->toHaveCount(2);

        expect($result[0]['@code'])->toBe('CUST001');
        expect($result[0]['@name'])->toBe('Test Customer OÜ');
        expect($result[0]['@email'])->toBe('test@example.com');
        expect($result[0]['@country'])->toBe('EE');

        expect($result[1]['@code'])->toBe('CUST002');
        expect($result[1]['@name'])->toBe('Another Company AS');
    });

    test('parses items XML into array', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('items.xml'));

        expect($result)->toBeArray();
        expect($result)->toHaveCount(2);

        expect($result[0]['@code'])->toBe('ITEM001');
        expect($result[0]['@name'])->toBe('Simple Service Item');
        expect($result[0]['@class'])->toBe('SERVICES');

        expect($result[1]['@code'])->toBe('ITEM002');
        expect($result[1]['@name'])->toBe('Container with Variants');
    });

    test('returns empty array for empty results', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('empty.xml'));

        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    });

    test('returns empty array for empty string', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse('');

        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    });

    test('converts empty strings to null by default', function () {
        $xml = '<?xml version="1.0"?><results><row><name></name></row></results>';
        $parser = new XmlResponseParser(treatEmptyAsNull: true);
        $result = $parser->parse($xml);

        expect($result[0]['name'])->toBeNull();
    });

    test('preserves empty strings when configured', function () {
        $xml = '<?xml version="1.0"?><results><row><name></name></row></results>';
        $parser = new XmlResponseParser(treatEmptyAsNull: false);
        $result = $parser->parse($xml);

        expect($result[0]['name'])->toBe('');
    });

    test('trims whitespace from values', function () {
        $xml = '<?xml version="1.0"?><results><row><name>  Test  </name></row></results>';
        $parser = new XmlResponseParser(trimStrings: true);
        $result = $parser->parse($xml);

        expect($result[0]['name'])->toBe('Test');
    });

    test('throws XmlParseException for invalid XML', function () {
        $parser = new XmlResponseParser();
        $parser->parse('not valid xml <broken');
    })->throws(XmlParseException::class, 'Failed to parse XML');

    test('XmlParseException contains error details', function () {
        $parser = new XmlResponseParser();

        try {
            $parser->parse('invalid xml');
        } catch (XmlParseException $e) {
            expect($e->getXmlErrors())->toBeArray();
            expect($e->getFormattedErrors())->toBeArray();
            expect($e->getRawXml())->toBe('invalid xml');
        }
    });

    test('parses attributes with @ prefix', function () {
        $xml = '<?xml version="1.0"?><results><item id="123" type="product"><name>Test</name></item></results>';
        $parser = new XmlResponseParser();
        $result = $parser->parse($xml);

        expect($result[0]['@id'])->toBe('123');
        expect($result[0]['@type'])->toBe('product');
        expect($result[0]['name'])->toBe('Test');
    });

    test('parses transport with container (multiple records)', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('transport-with-container.xml'));

        expect($result)->toHaveCount(2);
        expect($result[0]['@code'])->toBe('C001');
        expect($result[1]['@code'])->toBe('C002');
    });

    test('parses transport without container (multiple records)', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('transport-without-container.xml'));

        expect($result)->toHaveCount(2);
        expect($result[0]['@number'])->toBe('123');
        expect($result[1]['@number'])->toBe('124');
    });

    test('parses transport with single record', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('transport-single-record.xml'));

        expect($result)->toHaveCount(1);
        expect($result[0]['@code'])->toBe('C001');
        expect($result[0]['@name'])->toBe('Single Customer');
    });

    test('parses transport with single item having nested elements', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('transport-single-item.xml'));

        expect($result)->toHaveCount(1);
        expect($result[0]['@code'])->toBe('ITEM001');
        expect($result[0]['@name'])->toBe('Test Item');
        expect($result[0])->toHaveKey('datafields');
        expect($result[0])->toHaveKey('supplieritems');
    });

    test('parses transport with empty container', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('empty.xml'));

        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    });

    test('normalizes single vs multiple child elements consistently', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('receipts.xml'));

        expect($result)->toHaveCount(2);
        
        // First receipt has 2 rows
        expect($result[0]['rows']['row'])->toBeArray();
        expect($result[0]['rows']['row'])->toHaveCount(2);
        expect($result[0]['rows']['row'][0]['@invoice'])->toBe('INV001');
        expect($result[0]['rows']['row'][1]['@invoice'])->toBe('INV002');
        
        // Second receipt has 1 row - should still be in same structure (not nested differently)
        expect($result[1]['rows']['row'])->toBeArray();
        expect($result[1]['rows']['row'])->toHaveCount(1);
        expect($result[1]['rows']['row'][0]['@invoice'])->toBe('INV003');
    });
});
