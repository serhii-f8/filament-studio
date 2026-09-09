<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Tests\Fixtures\Flows;

use Flexpik\FilamentStudio\Contracts\Flows\FlowOperation;
use Flexpik\FilamentStudio\Contracts\Flows\OperationContext;
use Flexpik\FilamentStudio\Contracts\Flows\OperationResult;

/**
 * Sleeps for a measurable but sub-second period so step timing assertions have
 * something to measure. Second-precision timestamp columns cannot express it.
 */
class SlowOperation implements FlowOperation
{
    public const SLEEP_MICROSECONDS = 40_000;

    public function execute(OperationContext $context): OperationResult
    {
        usleep(self::SLEEP_MICROSECONDS);

        return OperationResult::success(['slept' => true]);
    }
}
