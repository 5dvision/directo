<?php

declare(strict_types=1);

namespace Directo\Schema;

use Directo\Endpoint\EndpointInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * CLI tool for downloading/updating XSD schemas.
 *
 * Downloads schemas from remote URLs to local resources/xsd/ directory.
 * Used by `composer schemas:update` or `bin/directo-schemas`.
 *
 * Design notes:
 * - Auto-discovers endpoint classes from src/Endpoint directory
 * - Simple: downloads files, no complex logic
 * - Idempotent: safe to run multiple times
 * - Reports progress for CLI usage
 */
final readonly class SchemaDownloader
{
    /**
     * Create a new schema downloader.
     *
     * @param  string  $outputPath  Local directory to save schemas
     * @param  string  $schemaBaseUrl  Base URL for downloading schemas
     * @param  ClientInterface|null  $httpClient  Optional HTTP client (for testing)
     */
    public function __construct(private string $outputPath, private string $schemaBaseUrl, private ?ClientInterface $httpClient = new Client([
        'timeout' => 30.0,
        'connect_timeout' => 10.0,
    ]))
    {
    }

    /**
     * Ensure the output directory exists.
     *
     * @throws \RuntimeException If directory cannot be created
     */
    private function ensureOutputDirectory(): void
    {
        if (is_dir($this->outputPath)) {
            return;
        }

        if (! mkdir($this->outputPath, 0755, true)) {
            throw new \RuntimeException(
                sprintf('Failed to create directory: %s', $this->outputPath),
            );
        }
    }

    /**
     * Discover all endpoint classes that implement EndpointInterface.
     *
     * @return array<class-string<EndpointInterface>>
     */
    private function discoverEndpointClasses(): array
    {
        $endpointDir = __DIR__.'/../Endpoint';
        $classes = [];

        if (! is_dir($endpointDir)) {
            return $classes;
        }

        foreach (glob($endpointDir.'/*Endpoint.php') as $file) {
            $filename = basename($file, '.php');
            $className = 'Directo\\Endpoint\\'.$filename;

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            // Skip abstract classes and interfaces
            if ($reflection->isAbstract()) {
                continue;
            }

            if ($reflection->isInterface()) {
                continue;
            }

            // Must implement EndpointInterface
            if (! $reflection->implementsInterface(EndpointInterface::class)) {
                continue;
            }

            $classes[] = $className;
        }

        return $classes;
    }

    /**
     * Get all schemas from endpoint classes.
     *
     * @return array<int, array{file: string, url: string}>
     */
    public function getAllSchemas(): array
    {
        $schemas = [];
        $seen = [];

        foreach ($this->discoverEndpointClasses() as $endpointClass) {
            $reflection = new \ReflectionClass($endpointClass);

            // Create instance without constructor to call schemas()
            $instance = $reflection->newInstanceWithoutConstructor();
            $endpointSchemas = $instance->schemas();

            foreach ($endpointSchemas as $schemaFile) {
                if ($schemaFile === null) {
                    continue;
                }

                if (isset($seen[$schemaFile])) {
                    continue;
                }

                $seen[$schemaFile] = true;
                $schemas[] = [
                    'file' => $schemaFile,
                    'url' => $this->schemaBaseUrl.$schemaFile,
                ];
            }
        }

        return $schemas;
    }

    /**
     * Download all registered schemas.
     *
     * @param  callable|null  $logger  Optional logger callback: fn(string $message): void
     * @return array{success: array<string, string>, failed: array<string, string>}
     */
    public function downloadAll(?callable $logger = null): array
    {
        $logger ??= fn (string $msg): null => null;

        $schemas = $this->getAllSchemas();
        $results = ['success' => [], 'failed' => []];

        try {
            $this->ensureOutputDirectory();
        } catch (\RuntimeException $runtimeException) {
            $logger('ERROR: ' . $runtimeException->getMessage());

            return $results;
        }

        foreach ($schemas as $schema) {
            $url = $schema['url'];
            $file = $schema['file'];
            $targetPath = $this->outputPath.'/'.$file;

            $logger('Downloading: ' . $url);

            try {
                $response = $this->httpClient->request('GET', $url);
                $content = (string) $response->getBody();

                if (file_put_contents($targetPath, $content) === false) {
                    $results['failed'][$file] = 'Failed to write file: ' . $targetPath;
                    $logger('  ERROR: Failed to write file');

                    continue;
                }

                $results['success'][$file] = $targetPath;
                $logger('  OK: Saved to ' . $file);
            } catch (GuzzleException $e) {
                $results['failed'][$file] = $e->getMessage();
                $logger('  ERROR: '.$e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Download a single schema by filename.
     *
     * @param  string  $schemaFile  The schema filename (e.g., 'ws_artiklid.xsd')
     * @return string Path to the downloaded file
     *
     * @throws \RuntimeException On download/write failure
     */
    public function download(string $schemaFile): string
    {
        $url = $this->schemaBaseUrl.$schemaFile;
        $targetPath = $this->outputPath.'/'.$schemaFile;

        $this->ensureOutputDirectory();

        try {
            $response = $this->httpClient->request('GET', $url);
            $content = (string) $response->getBody();

            if (file_put_contents($targetPath, $content) === false) {
                throw new \RuntimeException(
                    sprintf('Failed to write schema file: %s', $targetPath),
                );
            }

            return $targetPath;
        } catch (GuzzleException $guzzleException) {
            throw new \RuntimeException(
                sprintf('Failed to download schema from %s: %s', $url, $guzzleException->getMessage()),
                0,
                $guzzleException,
            );
        }
    }
}
