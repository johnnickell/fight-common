<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

/**
 * Interface McpReadableResourceProvider
 */
interface McpReadableResourceProvider extends McpResourceProvider
{
    /**
     * Returns current metadata for an exact owned URI without opening content
     *
     * Return null for an unowned identity, never a normalized or fallback target. Every listed URI must be
     * findable with consistent metadata, but owned URIs need not appear in discovery. Lookup must not read
     * content. All composed providers are checked for ownership before availability or opening.
     */
    public function find(string $uri): ?McpResourceInfo;

    /**
     * Opens only the selected Resource after Common has checked current availability
     *
     * Return a fresh readable stream positioned at its beginning, with matching URI/MIME/size metadata.
     * Do not prebuffer unbounded content. Common owns closing the returned stream on success and failure;
     * providers own cleanup if opening throws, storage containment, timeouts and their own bounded I/O.
     * Content and metadata must describe the same version during this read. No automatic retry is promised.
     */
    public function open(McpResourceInfo $resource): McpResourceContent;
}
