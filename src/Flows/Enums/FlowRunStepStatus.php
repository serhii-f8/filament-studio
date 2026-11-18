<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FlowRunStepStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
