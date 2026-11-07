<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreTables;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Test\Common\TestCase\EventSourcing\ConformanceEventMappingProvider;
use Fight\Test\Common\TestCase\EventSourcing\ConformanceOrderPlaced;

require dirname(__DIR__, 3).'/vendor/autoload.php';
require __DIR__.'/EventStoreConformanceTestCase.php';

$connection = DriverManager::getConnection(json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR));
$eventStore = new DbalEventStore($connection, new EventMapper([new ConformanceEventMappingProvider()]), new DbalEventStoreTables('Order', 'Group'));
fwrite(STDOUT, "attempting\n");
fflush(STDOUT);
$eventStore->append(new StreamId('order', 'second'), 0, [EventMessage::create(new ConformanceOrderPlaced('second'))]);
fwrite(STDOUT, "committed\n");
$connection->close();
