<?php

declare(strict_types=1);

use Directo\Config;
use Directo\Exception\SchemaValidationException;
use Directo\Schema\SchemaRegistry;

describe('SchemaRegistry', function (): void {
    test('returns correct schema path', function (): void {
        $registry = new SchemaRegistry('/custom/path', Config::DEFAULT_SCHEMA_BASE_URL);

        expect($registry->getSchemaPath('ws_kliendid.xsd'))->toBe('/custom/path/ws_kliendid.xsd');
        expect($registry->getSchemaPath('ws_artiklid.xsd'))->toBe('/custom/path/ws_artiklid.xsd');
    });

    test('returns schema base path', function (): void {
        $registry = new SchemaRegistry('/custom/path', Config::DEFAULT_SCHEMA_BASE_URL);

        expect($registry->getSchemaBasePath())->toBe('/custom/path');
    });

    test('returns correct schema URL', function (): void {
        $registry = new SchemaRegistry('/tmp', Config::DEFAULT_SCHEMA_BASE_URL);

        expect($registry->getSchemaUrl('ws_kliendid.xsd'))->toBe(
            'https://login.directo.ee/xmlcore/cap_xml_direct/ws_kliendid.xsd'
        );
    });

    test('returns custom schema base URL', function (): void {
        $customUrl = 'https://custom.example.com/schemas/';
        $registry = new SchemaRegistry('/tmp', $customUrl);

        expect($registry->getSchemaBaseUrl())->toBe($customUrl);
        expect($registry->getSchemaUrl('ws_kliendid.xsd'))->toBe(
            'https://custom.example.com/schemas/ws_kliendid.xsd'
        );
    });

    test('schemaFileExists returns false when file missing', function (): void {
        $registry = new SchemaRegistry('/nonexistent/path', Config::DEFAULT_SCHEMA_BASE_URL);

        expect($registry->schemaFileExists('ws_kliendid.xsd'))->toBeFalse();
    });

    test('schemaFileExists returns true when file exists', function (): void {
        $tempDir = sys_get_temp_dir().'/directo-test-'.uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir.'/ws_test.xsd', '<schema/>');

        $registry = new SchemaRegistry($tempDir, Config::DEFAULT_SCHEMA_BASE_URL);

        expect($registry->schemaFileExists('ws_test.xsd'))->toBeTrue();

        // Cleanup
        unlink($tempDir.'/ws_test.xsd');
        rmdir($tempDir);
    });
});

describe('Schema Validation', function (): void {
    beforeEach(function (): void {
        // Create a minimal valid XSD for testing
        $this->tempDir = sys_get_temp_dir().'/directo-test-'.uniqid();
        mkdir($this->tempDir);

        // Minimal XSD that accepts any XML
        $minimalXsd = <<<'XSD'
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="results">
        <xs:complexType>
            <xs:sequence>
                <xs:any minOccurs="0" maxOccurs="unbounded" processContents="lax"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD;

        file_put_contents($this->tempDir.'/ws_kliendid.xsd', $minimalXsd);
    });

    afterEach(function (): void {
        // Cleanup
        if (property_exists($this, 'tempDir') && $this->tempDir !== null && is_dir($this->tempDir)) {
            array_map(unlink(...), glob($this->tempDir.'/*'));
            rmdir($this->tempDir);
        }
    });

    test('validates XML against schema successfully', function (): void {
        $registry = new SchemaRegistry($this->tempDir, Config::DEFAULT_SCHEMA_BASE_URL);

        $xml = '<?xml version="1.0"?><results><customer><kood>TEST</kood></customer></results>';

        // Should not throw
        $registry->validateFile($xml, 'ws_kliendid.xsd');

        expect(true)->toBeTrue(); // If we get here, validation passed
    });

    test('throws SchemaValidationException on invalid XML', function (): void {
        // Create a strict XSD
        $strictXsd = <<<'XSD'
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="results">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="customer" minOccurs="1" maxOccurs="unbounded">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="kood" type="xs:string"/>
                        </xs:sequence>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD;

        file_put_contents($this->tempDir.'/ws_kliendid.xsd', $strictXsd);
        $registry = new SchemaRegistry($this->tempDir, Config::DEFAULT_SCHEMA_BASE_URL);

        // XML missing required 'kood' element
        $invalidXml = '<?xml version="1.0"?><results><customer><invalid>TEST</invalid></customer></results>';

        $registry->validateFile($invalidXml, 'ws_kliendid.xsd');
    })->throws(SchemaValidationException::class);

    test('SchemaValidationException contains error details', function (): void {
        $strictXsd = <<<'XSD'
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="results">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="customer" minOccurs="1"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD;

        file_put_contents($this->tempDir.'/ws_kliendid.xsd', $strictXsd);
        $registry = new SchemaRegistry($this->tempDir, Config::DEFAULT_SCHEMA_BASE_URL);

        try {
            $registry->validateFile('<?xml version="1.0"?><results></results>', 'ws_kliendid.xsd');
        } catch (SchemaValidationException $schemaValidationException) {
            expect($schemaValidationException->getValidationErrors())->toBeArray();
            expect($schemaValidationException->getFormattedErrors())->toBeArray();
            expect($schemaValidationException->getSchemaPath())->toContain('ws_kliendid.xsd');
        }
    });

    test('throws when schema file does not exist', function (): void {
        $registry = new SchemaRegistry('/nonexistent/path', Config::DEFAULT_SCHEMA_BASE_URL);

        $registry->validateFile('<results/>', 'ws_kliendid.xsd');
    })->throws(InvalidArgumentException::class, 'Schema file not found');
});
