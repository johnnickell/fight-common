<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Detail\QueryHandler;

use Fight\Common\Application\Mcp\Execution\Detail\Data\MessagePage;
use Fight\Common\Application\Mcp\Execution\Detail\Service\DetailReadAdmission;
use Fight\Common\Application\Mcp\Execution\Detail\Service\DetailReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class ListCapturedMessagesHandler
 */
final readonly class ListCapturedMessagesHandler implements QueryHandler
{
    /**
     * Constructs ListCapturedMessagesHandler
     */
    public function __construct(private DetailReadAdmission $admission, private DetailReader $reader)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return ListCapturedMessages::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?MessagePage
    {
        /**
         * @var ListCapturedMessages $query
         */
        $query = $queryMessage->payload();
        $access = $this->admission->currentAccess($query->selection());

        return $this->reader->messages($query, $access);
    }
}
