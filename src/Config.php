<?php

declare(strict_types=1);

namespace Directo;

/**
 * SDK configuration container.
 *
 * Immutable configuration object that holds all settings needed
 * to communicate with the Directo XMLCore API.
 *
 * Design notes:
 * - Immutable: once created, config cannot change (thread-safe, predictable)
 * - Uses constructor promotion for brevity
 * - Validates required fields at construction time (fail fast)
 *
 * @example Creating config with token only (recommended)
 * ```php
 * $config = new Config(
 *     token: 'your-api-token',
 * );
 * ```
 * @example Creating config with custom base URL
 * ```php
 * $config = new Config(
 *     token: 'your-api-token',
 *     baseUrl: 'https://custom.example.com/xmlcore.asp',
 * );
 * ```
 */
final readonly class Config
{
    public const DEFAULT_BASE_URL = 'https://login.directo.ee/xmlcore/cap_xml_direct/xmlcore.asp';

    public const DEFAULT_SCHEMA_BASE_URL = 'https://login.directo.ee/xmlcore/cap_xml_direct/';

    public const DEFAULT_TOKEN_PARAM = 'token';

    public const DEFAULT_TIMEOUT = 30.0;

    public const DEFAULT_CONNECT_TIMEOUT = 10.0;

    /**
     * @param  string  $token  API token value (never logged/exposed)
     * @param  string  $baseUrl  Full base URL for API requests
     * @param  string  $tokenParamName  Token parameter name ('token')
     * @param  float  $timeout  Request timeout in seconds
     * @param  float  $connectTimeout  Connection timeout in seconds
     * @param  bool  $validateSchema  Whether to validate responses against XSD
     * @param  bool  $treatEmptyAsNull  Convert empty strings to null in responses
     * @param  string|null  $schemaBasePath  Custom path to XSD schemas directory (local)
     * @param  string  $schemaBaseUrl  Base URL for downloading XSD schemas
     *
     * @throws \InvalidArgumentException If token is empty
     */
    public function __construct(
        public string $token,
        public string $baseUrl = self::DEFAULT_BASE_URL,
        public string $tokenParamName = self::DEFAULT_TOKEN_PARAM,
        public float $timeout = self::DEFAULT_TIMEOUT,
        public float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        public bool $validateSchema = false,
        public bool $treatEmptyAsNull = true,
        public ?string $schemaBasePath = null,
        public string $schemaBaseUrl = self::DEFAULT_SCHEMA_BASE_URL,
    ) {
        if ($token === '') {
            throw new \InvalidArgumentException('token is required');
        }
    }

    /**
     * Get the resolved schema base path (local directory).
     *
     * Falls back to the bundled schemas directory if not configured.
     *
     * @return string Local directory path containing XSD files
     */
    public function getSchemaBasePath(): string
    {
        if ($this->schemaBasePath !== null) {
            return $this->schemaBasePath;
        }

        return dirname(__DIR__).'/resources/xsd';
    }
}
