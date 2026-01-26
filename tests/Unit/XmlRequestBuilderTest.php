<?php

declare(strict_types=1);

use Directo\Parser\XmlRequestBuilder;

describe('XmlRequestBuilder', function () {
    test('builds item XML with code as attribute', function () {
        $builder = new XmlRequestBuilder();

        $xml = $builder->build('artiklid', 'artikkel', [
            'kood' => 'ITEM001',
            'nimetus' => 'Test Item',
            'klass' => 'CLASS1',
        ], 'kood');

        expect($xml)->toContain('<?xml version="1.0" encoding="UTF-8"?>');
        expect($xml)->toContain('<artiklid>');
        expect($xml)->toContain('<artikkel kood="ITEM001">');
        expect($xml)->toContain('<nimetus>Test Item</nimetus>');
        expect($xml)->toContain('<klass>CLASS1</klass>');
        expect($xml)->toContain('</artikkel>');
        expect($xml)->toContain('</artiklid>');
    });

    test('builds customer XML with code as attribute', function () {
        $builder = new XmlRequestBuilder();

        $xml = $builder->build('kliendid', 'klient', [
            'kood' => 'CUST001',
            'nimi' => 'Test Customer',
        ], 'kood');

        expect($xml)->toContain('<kliendid>');
        expect($xml)->toContain('<klient kood="CUST001">');
        expect($xml)->toContain('<nimi>Test Customer</nimi>');
        expect($xml)->toContain('</klient>');
        expect($xml)->toContain('</kliendid>');
    });

    test('builds XML without key attribute when not specified', function () {
        $builder = new XmlRequestBuilder();

        $xml = $builder->build('items', 'item', [
            'name' => 'Test',
            'value' => '123',
        ]);

        expect($xml)->toContain('<items>');
        expect($xml)->toContain('<item>');
        expect($xml)->toContain('<name>Test</name>');
        expect($xml)->not->toContain('kood=');
    });

    test('handles boolean values', function () {
        $builder = new XmlRequestBuilder();

        $xml = $builder->build('artiklid', 'artikkel', [
            'aktiivne' => true,
            'suletud' => false,
        ]);

        expect($xml)->toContain('<aktiivne>1</aktiivne>');
        expect($xml)->toContain('<suletud>0</suletud>');
    });

    test('handles null values as empty strings', function () {
        $builder = new XmlRequestBuilder();

        $xml = $builder->build('artiklid', 'artikkel', [
            'kirjeldus' => null,
        ]);

        expect($xml)->toContain('<kirjeldus></kirjeldus>');
    });

    test('handles numeric values', function () {
        $builder = new XmlRequestBuilder();

        $xml = $builder->build('artiklid', 'artikkel', [
            'hind' => 99.99,
            'kogus' => 100,
        ]);

        expect($xml)->toContain('<hind>99.99</hind>');
        expect($xml)->toContain('<kogus>100</kogus>');
    });

    test('buildBatch creates multiple records', function () {
        $builder = new XmlRequestBuilder();

        $xml = $builder->buildBatch('artiklid', 'artikkel', [
            ['kood' => 'ITEM001', 'nimetus' => 'Item 1'],
            ['kood' => 'ITEM002', 'nimetus' => 'Item 2'],
        ], 'kood');

        expect($xml)->toContain('<artiklid>');
        expect($xml)->toContain('<artikkel kood="ITEM001">');
        expect($xml)->toContain('<nimetus>Item 1</nimetus>');
        expect($xml)->toContain('<artikkel kood="ITEM002">');
        expect($xml)->toContain('<nimetus>Item 2</nimetus>');
        expect($xml)->toContain('</artiklid>');
    });

    test('handles nested associative arrays', function () {
        $builder = new XmlRequestBuilder();

        $xml = $builder->build('artiklid', 'artikkel', [
            'dimensions' => [
                'width' => '10',
                'height' => '20',
            ],
        ]);

        expect($xml)->toContain('<dimensions>');
        expect($xml)->toContain('<width>10</width>');
        expect($xml)->toContain('<height>20</height>');
        expect($xml)->toContain('</dimensions>');
    });

    test('handles list arrays with same element name', function () {
        $builder = new XmlRequestBuilder();

        $xml = $builder->build('artiklid', 'artikkel', [
            'tag' => ['electronics', 'sale', 'featured'],
        ]);

        expect($xml)->toContain('<tag>electronics</tag>');
        expect($xml)->toContain('<tag>sale</tag>');
        expect($xml)->toContain('<tag>featured</tag>');
    });

    test('handles custom element names for any endpoint', function () {
        $builder = new XmlRequestBuilder();

        // Can build XML for any endpoint without modifying XmlRequestBuilder
        $xml = $builder->build('tellimused', 'tellimus', [
            'number' => 'ORD001',
            'klient' => 'CUST001',
        ], 'number');

        expect($xml)->toContain('<tellimused>');
        expect($xml)->toContain('<tellimus number="ORD001">');
        expect($xml)->toContain('<klient>CUST001</klient>');
    });
});
