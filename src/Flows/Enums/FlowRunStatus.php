<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FlowRunStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Paused = 'paused';
    case Aborted = 'aborted';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled, self::Aborted], true);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
            self::Paused => 'warning',
            self::Aborted => 'danger',
        };
    }

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
