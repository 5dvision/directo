<?php

declare(strict_types=1);

use Directo\Schema\SchemaDownloader;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\FulfilledPromise;

// Mocks
class MockHttpClient implements ClientInterface
{
    public array $requests = [];
    public ?ResponseInterface $response = null;

    public function request(string $method, $uri, array $options = []): ResponseInterface
    {
        $this->requests[] = ['method' => $method, 'uri' => $uri, 'options' => $options];
        return $this->response ?? new Response(200);
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->response ?? new Response(200);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return new FulfilledPromise($this->response ?? new Response(200));
    }

    public function requestAsync(string $method, $uri, array $options = []): PromiseInterface
    {
        return new FulfilledPromise($this->response ?? new Response(200));
    }

    public function getConfig(?string $option = null)
    {
        return null;
    }
}

describe('SchemaDownloader', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir() . '/directo-test-dl-' . uniqid();
        $this->httpClient = new MockHttpClient();
        $this->baseUrl = 'https://example.com/schemas/';

        $this->downloader = new SchemaDownloader(
            $this->tempDir,
            $this->baseUrl,
            $this->httpClient
        );
    });

    afterEach(function (): void {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
    });

    test('downloads single schema', function (): void {
        $schemaContent = '<schema>test</schema>';
        $this->httpClient->response = new Response(200, [], $schemaContent);

        $path = $this->downloader->download('test.xsd');

        expect(file_exists($path))->toBeTrue();
        expect(file_get_contents($path))->toBe($schemaContent);

        expect($this->httpClient->requests)->toHaveCount(1);
        expect($this->httpClient->requests[0]['uri'])->toBe('https://example.com/schemas/test.xsd');
    });

    test('creates output directory if missing', function (): void {
        // Remove temp dir created by setUp to test creation
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        $this->httpClient->response = new Response(200, [], 'content');

        $this->downloader->download('test.xsd');

        expect(is_dir($this->tempDir))->toBeTrue();
    });
});
