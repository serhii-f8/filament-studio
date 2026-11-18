<?php

declare(strict_types=1);

namespace Flexpik\FilamentStudio\Flows\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FlowStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
