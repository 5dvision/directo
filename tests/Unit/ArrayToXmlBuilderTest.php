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
