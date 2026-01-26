<?php

declare(strict_types=1);

use Directo\Config;

describe('Config', function () {
    test('creates config with token only using default URL', function () {
        $config = new Config(token: 'test-key');

        expect($config->baseUrl)->toBe('https://login.directo.ee/xmlcore/cap_xml_direct/xmlcore.asp');
        expect($config->token)->toBe('test-key');
        expect($config->tokenParamName)->toBe('token');
    });

    test('creates config with custom base URL', function () {
        $config = new Config(
            token: 'test-key',
            baseUrl: 'https://custom.directo.ee/xmlcore/test/xmlcore.asp',
        );

        expect($config->baseUrl)->toBe('https://custom.directo.ee/xmlcore/test/xmlcore.asp');
    });

    test('throws if token is empty', function () {
        new Config(token: '');
    })->throws(InvalidArgumentException::class, 'token is required');

    test('uses custom token param name', function () {
        $config = new Config(
            token: 'test-key',
            tokenParamName: 'key',
        );

        expect($config->tokenParamName)->toBe('key');
    });

    test('uses default timeouts', function () {
        $config = new Config(token: 'test-key');

        expect($config->timeout)->toBe(30.0);
        expect($config->connectTimeout)->toBe(10.0);
    });

    test('uses custom timeouts', function () {
        $config = new Config(
            token: 'test-key',
            timeout: 60.0,
            connectTimeout: 5.0,
        );

        expect($config->timeout)->toBe(60.0);
        expect($config->connectTimeout)->toBe(5.0);
    });

    test('schema validation is disabled by default', function () {
        $config = new Config(token: 'test-key');

        expect($config->validateSchema)->toBeFalse();
    });

    test('treat empty as null is enabled by default', function () {
        $config = new Config(token: 'test-key');

        expect($config->treatEmptyAsNull)->toBeTrue();
    });

    test('returns default schema base path', function () {
        $config = new Config(token: 'test-key');

        expect($config->getSchemaBasePath())->toContain('resources/xsd');
    });

    test('returns custom schema base path', function () {
        $config = new Config(
            token: 'test-key',
            schemaBasePath: '/custom/path',
        );

        expect($config->getSchemaBasePath())->toBe('/custom/path');
    });
});
