<?php

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Flexpik\FilamentStudio\Flows\Enums\FlowRunStatus;
use Flexpik\FilamentStudio\Flows\Enums\FlowRunStepStatus;
use Flexpik\FilamentStudio\Flows\Enums\FlowStatus;

it('gives flow, run and step statuses a badge colour and a readable label', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect($case)->toBeInstanceOf(HasColor::class)->toBeInstanceOf(HasLabel::class)
            ->and($case->getColor())->toBeString()->not->toBeEmpty()
            ->and($case->getLabel())->toBe(ucfirst($case->value));
    }
})->with([FlowStatus::class, FlowRunStatus::class, FlowRunStepStatus::class]);

it('tells success and failure apart at a glance', function () {
    expect(FlowRunStatus::Completed->getColor())->toBe('success')
        ->and(FlowRunStatus::Failed->getColor())->toBe('danger')
        ->and(FlowRunStepStatus::Failed->getColor())->toBe('danger')
        ->and(FlowStatus::Active->getColor())->toBe('success')
        ->and(FlowStatus::Inactive->getColor())->toBe('gray');
});
