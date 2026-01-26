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

        expect($result[0]['kood'])->toBe('CUST001');
        expect($result[0]['nimi'])->toBe('Test Customer OÜ');
        expect($result[0]['email'])->toBe('test@example.com');
        expect($result[0]['riik'])->toBe('EE');

        expect($result[1]['kood'])->toBe('CUST002');
        expect($result[1]['nimi'])->toBe('Another Company AS');
    });

    test('parses items XML into array', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('items.xml'));

        expect($result)->toBeArray();
        expect($result)->toHaveCount(2);

        expect($result[0]['kood'])->toBe('ITEM001');
        expect($result[0]['nimetus'])->toBe('Test Product');
        expect($result[0]['klass'])->toBe('ELECTRONICS');
        expect($result[0]['hind'])->toBe('99.99');

        expect($result[1]['kood'])->toBe('ITEM002');
        expect($result[1]['nimetus'])->toBe('Another Product');
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

    test('handles nested elements', function () {
        $parser = new XmlResponseParser();
        $result = $parser->parse(fixture('nested.xml'));

        expect($result)->toHaveCount(1);
        expect($result[0]['kood'])->toBe('ITEM003');

        // Nested elements become nested arrays
        expect($result[0]['attributes'])->toBeArray();
        expect($result[0]['attributes']['color'])->toBe('Red');
        expect($result[0]['attributes']['size'])->toBe('Large');

        // Repeated elements with attributes
        expect($result[0]['prices'])->toBeArray();
        expect($result[0]['prices']['price'])->toBeArray();
    });

    test('converts empty strings to null by default', function () {
        $parser = new XmlResponseParser(treatEmptyAsNull: true);
        $result = $parser->parse(fixture('items.xml'));

        // nimetus2 is empty in ITEM002
        expect($result[1]['nimetus2'])->toBeNull();
    });

    test('preserves empty strings when configured', function () {
        $parser = new XmlResponseParser(treatEmptyAsNull: false);
        $result = $parser->parse(fixture('items.xml'));

        // nimetus2 is empty in ITEM002
        expect($result[1]['nimetus2'])->toBe('');
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
});
