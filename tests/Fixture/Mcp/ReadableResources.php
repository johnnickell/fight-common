<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\Resource\McpReadableResourceProvider;
use Fight\Common\Application\Mcp\Resource\McpResourceContent;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use GuzzleHttp\Psr7\Utils;
use RuntimeException;

final class ReadableResources implements McpReadableResourceProvider
{
    public array $opened = [];
    public array $lookedUp = [];
    public int $enumerations = 0;
    public bool $fail = false;
    public ?McpResourceInfo $returnedInfo = null;

    public function __construct(public array $entries) {}

    public function resources(): iterable
    {
        ++$this->enumerations;
        $entries = $this->entries;
        ksort($entries, SORT_STRING);
        foreach ($entries as $uri => $entry) {
            if ($entry['listed'] ?? true) { yield $this->info($uri); }
        }
    }

    public function find(string $uri): ?McpResourceInfo
    {
        $this->lookedUp[] = $uri;
        return array_key_exists($uri, $this->entries) ? $this->info($uri) : null;
    }

    public function open(McpResourceInfo $resource): McpResourceContent
    {
        $this->opened[] = $resource->uri();
        if ($this->fail) { throw new RuntimeException('private storage credential'); }
        $entry = $this->entries[$resource->uri()];
        $info = $this->returnedInfo ?? $resource;
        $stream = Utils::streamFor($entry['bytes']);
        return ($entry['binary'] ?? false) ? McpResourceContent::binary($info, $stream) : McpResourceContent::text($info, $stream);
    }

    private function info(string $uri): McpResourceInfo
    {
        $entry = $this->entries[$uri];
        return McpResourceInfo::fromArray(['uri' => $uri, 'name' => $uri, 'mimeType' => $entry['mimeType'] ?? 'text/plain', 'size' => $entry['size'] ?? strlen($entry['bytes'])]);
    }
}
