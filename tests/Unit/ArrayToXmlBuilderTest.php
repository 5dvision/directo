<?php

declare(strict_types=1);

use Directo\Http\ArrayToXmlBuilder;
use Directo\Exception\SchemaValidationException;

describe('ArrayToXmlBuilder Schema Validation', function (): void {
    beforeEach(function (): void {
        // Create a minimal valid XSD for testing
        $this->tempDir = sys_get_temp_dir() . '/directo-test-atx-' . uniqid();
        mkdir($this->tempDir);

        $minimalXsd = <<<'XSD'
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="root">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="item" type="xs:string"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD;

        file_put_contents($this->tempDir . '/test.xsd', $minimalXsd);
        $this->xsdPath = $this->tempDir . '/test.xsd';
    });

    afterEach(function (): void {
        // Cleanup
        if (file_exists($this->xsdPath)) {
            unlink($this->xsdPath);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    });

    test('validateXml passes with valid XML', function (): void {
        $validXml = '<?xml version="1.0"?><root><item>test</item></root>';

        expect(ArrayToXmlBuilder::validateXml($validXml, $this->xsdPath))->toBeTrue();
    });

    test('validateXml throws SchemaValidationException on invalid XML', function (): void {
        $invalidXml = '<?xml version="1.0"?><root><invalid>test</invalid></root>';

        ArrayToXmlBuilder::validateXml($invalidXml, $this->xsdPath);
    })->throws(SchemaValidationException::class);

    test('validateXml throws InvalidArgumentException when schema file missing', function (): void {
        ArrayToXmlBuilder::validateXml('<root/>', '/non/existent/path.xsd');
    })->throws(InvalidArgumentException::class);
});

describe('ArrayToXmlBuilder Conversion', function (): void {
    test('converts simple array to xml', function (): void {
        $data = ['item' => 'value'];
        $xml = ArrayToXmlBuilder::arrayToXml($data, 'root');

        expect($xml)->toContain('<root><item>value</item></root>');
    });

    test('handles attributes with @attributes key', function (): void {
        $data = [
            'item' => [
                '@attributes' => ['id' => '123'],
                '@value' => 'value'
            ]
        ];
        $xml = ArrayToXmlBuilder::arrayToXml($data, 'root');

        expect($xml)->toContain('<item id="123">value</item>');
    });

    test('handles nested arrays', function (): void {
        $data = [
            'items' => [
                'item' => [
                    ['name' => 'Item 1'],
                    ['name' => 'Item 2'],
                ]
            ]
        ];
        $xml = ArrayToXmlBuilder::arrayToXml($data, 'root');

        expect($xml)->toContain('<items><item><name>Item 1</name></item><item><name>Item 2</name></item></items>');
    });

    test('handles CDATA with @cdata key', function (): void {
        $data = ['description' => ['@cdata' => 'Need <b>escaping</b>']];
        $xml = ArrayToXmlBuilder::arrayToXml($data, 'root');

        expect($xml)->toContain('<![CDATA[Need <b>escaping</b>]]>');
    });

    test('pretty prints output when requested', function (): void {
        $data = ['item' => 'value'];
        $xml = ArrayToXmlBuilder::arrayToXml($data, 'root', pretty: true);

        expect($xml)->toContain("\n");
        expect($xml)->toContain("  <item>value</item>");
    });

    test('sanitizes special characters', function (): void {
        $data = ['item' => 'a & b'];
        $xml = ArrayToXmlBuilder::arrayToXml($data, 'root');

        expect($xml)->toContain('a &amp; b');
    });

    test('handles root element replacement logic', function (): void {
        // Case where root element is NOT provided (default 'root'), checking regex replacement if it was needed
        // But usually we provide root element.
        // Let's test the path where rootElement is null in signature but used internally
        $data = ['item' => 'value'];
        // The signature is arrayToXml(array $data, ?string $rootElement = null, ...)
        // If rootElement is null, it defaults to 'root' inside.

        $xml = ArrayToXmlBuilder::arrayToXml($data);
        // It should return just the content if rootElement was null (the method returns inner XML if root is null? No, wait)

        /*
            if ($rootElement !== null) {
                return $xmlString;
            }
            return preg_replace(...)
         */

        // So if rootElement is null, it tries to strip the root wrapper?
        // Let's verify the code behavior from `ArrayToXmlBuilder.php`
        // Lines 50-58: if rootElement !== null return xmlString.
        // Else: replace <root>...</root> with ... (stripping root)

        $xml = ArrayToXmlBuilder::arrayToXml(['item' => 'value']);
        // Should NOT contain <root>
        expect($xml)->not->toContain('<root>');
        expect($xml)->toContain('<item>value</item>');
    });
});
