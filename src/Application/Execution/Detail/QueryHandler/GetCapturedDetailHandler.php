<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Detail\QueryHandler;

use Fight\Common\Application\Execution\Detail\Data\CapturedDetail;
use Fight\Common\Application\Execution\Detail\Service\DetailReadAdmission;
use Fight\Common\Application\Execution\Detail\Service\DetailReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Execution\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class GetCapturedDetailHandler
 */
final readonly class GetCapturedDetailHandler implements QueryHandler
{
    /**
     * Constructs GetCapturedDetailHandler
     */
    public function __construct(private DetailReadAdmission $admission, private DetailReader $reader)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return GetCapturedDetail::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?CapturedDetail
    {
        /**
         * @var GetCapturedDetail $query
         */
        $query = $queryMessage->payload();
        $access = $this->admission->currentAccess($query->selection());

        return $this->reader->get($query, $access);
    }
}
