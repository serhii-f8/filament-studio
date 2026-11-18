{{-- Inline styles only: package views are not compiled into the host panel's Tailwind build. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        @if($this->panel->header_visible && $this->panel->header_label)
            <div class="fi-wi-stats-overview-stat-label" style="margin-bottom: 0.75rem;">
                {{ $this->panel->header_label }}
            </div>
        @endif
        @php
            $value = $this->getValue();
            $percentage = $this->getPercentage();
            $size = $this->config('size', 'full') === 'half' ? 'half' : 'full';
            $strokeWidth = (int) $this->config('stroke_width', 10);
            $color = $this->getStrokeColor();
            $rounded = $this->config('rounded_stroke', true);
            $precision = (int) $this->config('decimal_precision', 0);
            $radius = 45;
            $circumference = $size === 'half' ? $radius * M_PI : 2 * $radius * M_PI;
            $offset = $circumference - ($percentage / 100) * $circumference;
        @endphp
        <div style="display: flex; flex-direction: column; align-items: center;">
            <div style="position: relative; width: 100%; max-width: 180px;">
                <svg viewBox="0 0 100 {{ $size === 'half' ? '55' : '100' }}" style="display: block; width: 100%; height: auto;" role="img" aria-label="{{ round($percentage) }}%">
                    <circle
                        cx="50" cy="50" r="{{ $radius }}"
                        fill="none"
                        style="stroke: color-mix(in oklab, currentColor 12%, transparent);"
                        stroke-width="{{ $strokeWidth }}"
                        @if($size === 'half') stroke-dasharray="{{ $radius * M_PI }} {{ 2 * $radius * M_PI }}" transform="rotate(180, 50, 50)" @endif
                    />
                    <circle
                        cx="50" cy="50" r="{{ $radius }}"
                        fill="none"
                        stroke="{{ $color }}"
                        stroke-width="{{ $strokeWidth }}"
                        stroke-dasharray="{{ $circumference }}"
                        stroke-dashoffset="{{ $offset }}"
                        stroke-linecap="{{ $rounded ? 'round' : 'butt' }}"
                        transform="rotate({{ $size === 'half' ? 180 : -90 }}, 50, 50)"
                        style="transition: stroke-dashoffset 500ms ease;"
                    />
                </svg>
                <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; {{ $size === 'half' ? 'padding-top: 30%;' : '' }}">
                    <span style="font-size: 1.5rem; font-weight: 600; letter-spacing: -0.025em;">{{ round($percentage) }}%</span>
                </div>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.875rem; opacity: 0.7;">
                {{ number_format($value, $precision) }} / {{ number_format($this->getMaximum(), $precision) }}
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
