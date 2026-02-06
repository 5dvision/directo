<?php

declare(strict_types=1);

namespace Directo\Endpoint;

use Directo\Http\ArrayToXmlBuilder;

/**
 * Generic endpoint for custom Directo XMLCore queries.
 *
 * Allows using the SDK's infrastructure (transport, parsing, error handling)
 * for endpoints that don't have dedicated classes in the SDK.
 *
 * Useful for:
 * - Company-specific custom endpoints
 * - Beta/new endpoints not yet added to SDK
 * - One-off queries
 */
final class XmlCoreEndpoint extends AbstractEndpoint
{
    /** @var string The 'what' parameter (e.g. 'invoice') */
    private string $what;

    /** @var array<string, string> */
    private array $schemas = [];

    public function setWhat(string $what): self
    {
        $this->what = $what;

        return $this;
    }

    public function what(): string
    {
        return $this->what;
    }

    public function allowedFilters(): array
    {
        // Custom endpoints open by default, since we can't know validation rules
        return [];
    }

    public function validateFilters(array $filters): void
    {
        // Skip validation for custom endpoints as we don't know allowed filters
    }

    public function xmlElements(): array
    {
        //Skip XML elements definition
        return [];
    }

    public function setSchemas(array $schemas): self
    {
        $this->schemas = $schemas;

        return $this;
    }

    public function schemas(): array
    {
        return $this->schemas;
    }

    /**
     * Converts arbitrary array to XML and sends as PUT request.
     *
     * @param  array<string, mixed>  $data  Full array structure (including root element)
     * @return array<string, mixed>
     */
    public function putArray(array $data): array
    {
        $xml = ArrayToXmlBuilder::arrayToXml($data);

        return $this->putRaw($xml);
    }
}
