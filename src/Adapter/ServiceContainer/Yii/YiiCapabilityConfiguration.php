<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Mailer\MailerInterface;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\View\ViewInterface;

/**
 * Class YiiCapabilityConfiguration
 *
 * Creates explicitly selected Yii capability-group definitions.
 */
final class YiiCapabilityConfiguration
{
    /**
     * Returns persistence collaborators using their standard contracts directly
     *
     * @return array<string, mixed>
     */
    public static function persistence(
        ConnectionInterface $connection,
        CacheInterface $cache,
        LoggerInterface $logger
    ): array {
        return [
            CacheInterface::class      => $cache,
            LoggerInterface::class     => $logger,
            SchemaCache::class         => static fn (CacheInterface $cache): SchemaCache => new SchemaCache($cache),
            ConnectionInterface::class => static function () use ($connection, $logger): ConnectionInterface {
                if ($connection instanceof LoggerAwareInterface) {
                    $connection->setLogger($logger);
                }

                return $connection;
            }
        ];
    }

    /**
     * Returns the native Yii routing collaborator definition
     *
     * @return array<string, mixed>
     */
    public static function routing(UrlGeneratorInterface $urlGenerator): array
    {
        return [UrlGeneratorInterface::class => $urlGenerator];
    }

    /**
     * Returns reusable synchronous messaging collaborator definitions
     *
     * @return array<string, mixed>
     */
    public static function messaging(
        SynchronousCommandBus $commandBus,
        SynchronousEventDispatcher $eventDispatcher
    ): array {
        return [
            SynchronousCommandBus::class      => $commandBus,
            SynchronousEventDispatcher::class => $eventDispatcher
        ];
    }

    /**
     * Returns the configured Fight HTTP transport definition
     *
     * @return array<string, mixed>
     */
    public static function http(HttpClient $httpClient): array
    {
        return [HttpClient::class => $httpClient];
    }

    /**
     * Returns the selected Symfony Mail fallback collaborator definition
     *
     * @return array<string, mixed>
     */
    public static function mail(MailerInterface $mailer): array
    {
        return [MailerInterface::class => $mailer];
    }

    /**
     * Returns the application-selected Yii View collaborator and template root
     *
     * @return array<string, mixed>
     */
    public static function view(ViewInterface $view, string $templatesPath): array
    {
        return [
            ViewInterface::class   => $view,
            'fight.templates_path' => static fn (): string => $templatesPath
        ];
    }

    /**
     * Returns no filesystem policy because paths and policy remain application-owned
     *
     * @return array<string, mixed>
     */
    public static function filesystem(): array
    {
        return [];
    }
}
