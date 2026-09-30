<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Skill;

use Fight\Common\Application\Mcp\Resource\McpProtectedResourceProvider;
use Fight\Common\Application\Mcp\Resource\McpResourceContent;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Domain\Exception\DomainException;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class McpSkillResources
 */
final readonly class McpSkillResources implements McpProtectedResourceProvider
{
    /**
     * @var array<string, McpSkillRevision>
     */
    private array $owners;
    /**
     * @var array<string, McpResourceInfo>
     */
    private array $resources;

    /**
     * Constructs McpSkillResources
     *
     * Supply only validated snapshots. The finite catalog budget includes concealed and historical files.
     * Shared Resource dispatch enforces this provider's whole-Skill decision as well as its general policy;
     * no consumer composition can accidentally omit the Skill decision by supplying a permissive Resource policy.
     *
     * @phpstan-param iterable<mixed> $revisions
     */
    public function __construct(
        iterable $revisions,
        private McpSkillAvailability $availability,
        private StreamFactoryInterface $streams,
        int $maxResources = 10000
    ) {
        if ($maxResources < 1 || $maxResources > 1000000) {
            throw new DomainException('Skill Resource catalogs require a finite positive file budget.');
        }

        $owners = [];
        $resources = [];
        foreach ($revisions as $revision) {
            if (!$revision instanceof McpSkillRevision) {
                throw new DomainException('Skill Resource providers require validated immutable revisions.');
            }

            foreach ($revision->resources() as $uri => $info) {
                if (isset($owners[$uri]) || count($owners) >= $maxResources) {
                    throw new DomainException('Skill Resource ownership is duplicated or exceeds its catalog budget.');
                }

                $owners[$uri] = $revision;
                $resources[$uri] = $info;
            }
        }

        ksort($resources, SORT_STRING);
        $this->owners = $owners;
        $this->resources = $resources;
    }

    /**
     * @inheritDoc
     */
    public function resources(): iterable
    {
        return array_values($this->resources);
    }

    /**
     * @inheritDoc
     */
    public function find(string $uri): ?McpResourceInfo
    {
        return $this->resources[$uri] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(McpResourceInfo $resource): bool
    {
        $owner = $this->owners[$resource->uri()] ?? null;

        return $owner !== null && $this->availability->isAvailable($owner->entry());
    }

    /**
     * @inheritDoc
     */
    public function open(McpResourceInfo $resource): McpResourceContent
    {
        // Recheck even direct provider callers before allocating a stream. Denials through Resource dispatch
        // occur before open and retain its indistinguishable unknown/unavailable invalid-parameters response.
        if (!$this->isAvailable($resource) || $this->find($resource->uri())?->toArray() !== $resource->toArray()) {
            throw new DomainException('Skill Resource is unavailable or does not match its immutable descriptor.');
        }

        $file = $this->owners[$resource->uri()]->file($resource->uri());
        $stream = $this->streams->createStream($file->bytes);

        if ($file->text) {
            return McpResourceContent::text($resource, $stream, $file->digest);
        }

        return McpResourceContent::binary($resource, $stream, $file->digest);
    }
}
