<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriberReader;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\Subscription\Exception\SubscriberReadFailed;
use Fight\Common\Application\Mcp\Subscription\QueryHandler\ListSubscribersHandler;
use Fight\Common\Application\Mcp\Subscription\QueryHandler\SummarizeSubscribersHandler;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberAccess;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberReadAdmission;
use Fight\Common\Domain\Mcp\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Mcp\Subscription\Query\SummarizeSubscribers;
use Fight\Common\Domain\Mcp\Subscription\SubscriberSelection;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$params = json_decode(file_get_contents($argv[1].'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
$db = DriverManager::getConnection($params);
$data = json_decode(file_get_contents($argv[1].'/inspection.json'), true, flags: JSON_THROW_ON_ERROR);
// Fixture-owned current policy is separate from all serialized Query arguments and listen admission.
$admission = new class implements SubscriberReadAdmission {
    public array $scopes = [];
    public ?array $registrations = null;
    public int $calls = 0;
    public function currentAccess(SubscriberSelection $selection): SubscriberAccess { ++$this->calls; return SubscriberAccess::create($this->scopes, $this->registrations); }
};
$router = new InMemoryQueryRouter();
$reader = new DbalSubscriberReader($db, 'package-shared-registration-db');
foreach ([new SummarizeSubscribersHandler($admission, $reader), new ListSubscribersHandler($admission, $reader)] as $handler) { $router->registerHandler($handler::queryRegistration(), $handler); }
$bus = new RoutingQueryBus($router);
$results = [];
foreach ($data as $operation) {
    $admission->scopes = $operation['granted_scopes']; $admission->registrations = $operation['granted_registrations'] ?? null;
    $selection = SubscriberSelection::create($operation['scopes'], $operation['filters'] ?? []);
    $query = $operation['query'] === 'summary' ? new SummarizeSubscribers($selection) : new ListSubscribers($selection, $operation['page'] ?? 1, $operation['per_page'] ?? 1, $operation['orderings'] ?? []);
    try { $results[] = $bus->fetch($query)->toArray(); }
    catch (SubscriberReadFailed $error) { $results[] = ['failure' => $error::class, 'message' => $error->getMessage()]; }
}
echo json_encode(['process' => getmypid(), 'admissions' => $admission->calls, 'results' => $results, 'transaction_active' => $db->isTransactionActive()], JSON_THROW_ON_ERROR);
