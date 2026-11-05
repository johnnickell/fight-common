<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Observability\Metrics;

use Fight\Common\Adapter\Observability\Metrics\NullMetricsCollector;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NullMetricsCollector::class)]
class NullMetricsCollectorTest extends UnitTestCase
{
    private NullMetricsCollector $metricsCollector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsCollector = new NullMetricsCollector();
    }

    public function test_that_increment_is_a_no_op(): void
    {
        $this->metricsCollector->increment('some.metric', ['tag' => 'value']);
        $this->addToAssertionCount(1);
    }

    public function test_that_gauge_is_a_no_op(): void
    {
        $this->metricsCollector->gauge('some.metric', 42.0, ['tag' => 'value']);
        $this->addToAssertionCount(1);
    }

    public function test_that_histogram_is_a_no_op(): void
    {
        $this->metricsCollector->histogram('some.metric', 100.0, ['tag' => 'value']);
        $this->addToAssertionCount(1);
    }
}
