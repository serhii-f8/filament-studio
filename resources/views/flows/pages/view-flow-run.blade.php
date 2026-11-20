{{-- Inline styles + Filament fi-* components only: package views are not compiled into the host panel's Tailwind build.
     Colours come from Filament's theme variables or color-mix() on currentColor, so dark mode follows the panel. --}}
@php
    $muted = 'opacity: 0.7;';
    $border = 'border: 1px solid color-mix(in oklab, currentColor 12%, transparent);';
    $codeBlock = 'margin: 0.375rem 0 0; padding: 0.625rem 0.75rem; overflow: auto; max-height: 24rem; border-radius: 0.375rem; font-size: 0.75rem; line-height: 1.5; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre;';
    $runStatus = $run->status;
@endphp
<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        {{-- Run metadata --}}
        <x-filament::section>
            <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: 1rem 1.5rem; margin: 0; font-size: 0.875rem;">
                <div>
                    <dt style="{{ $muted }} font-size: 0.75rem; font-weight: 500;">Flow</dt>
                    <dd style="margin: 0.25rem 0 0; font-weight: 600;">{{ $record->name }}</dd>
                </div>
                <div>
                    <dt style="{{ $muted }} font-size: 0.75rem; font-weight: 500;">Version</dt>
                    <dd style="margin: 0.25rem 0 0;">{{ $run->flow_version_id ?? 'draft' }}</dd>
                </div>
                <div>
                    <dt style="{{ $muted }} font-size: 0.75rem; font-weight: 500;">Status</dt>
                    <dd style="margin: 0.25rem 0 0; display: flex;">
                        @if ($runStatus)
                            <x-filament::badge :color="$runStatus->getColor()">{{ $runStatus->getLabel() }}</x-filament::badge>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt style="{{ $muted }} font-size: 0.75rem; font-weight: 500;">Trigger</dt>
                    <dd style="margin: 0.25rem 0 0;">{{ $run->trigger_type }}</dd>
                </div>
                <div>
                    <dt style="{{ $muted }} font-size: 0.75rem; font-weight: 500;">Started</dt>
                    <dd style="margin: 0.25rem 0 0;">{{ $run->started_at?->diffForHumans() ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="{{ $muted }} font-size: 0.75rem; font-weight: 500;">Duration</dt>
                    <dd style="margin: 0.25rem 0 0;">{{ $run->duration_ms ?? '—' }}ms</dd>
                </div>
            </dl>
        </x-filament::section>

        {{-- Step timeline grouped by operation_key --}}
        <x-filament::section heading="Steps">
            <div style="{{ $border }} border-radius: 0.5rem; overflow: hidden;">
                @foreach ($this->groupedSteps as $operationKey => $attempts)
                    @php $multipleAttempts = $attempts->count() > 1; @endphp
                    @foreach ($attempts->sortBy('attempt_number') as $step)
                        @php
                            // Fall back to the timestamp delta for steps recorded before
                            // duration_ms existed; those columns are second-precision.
                            $durationMs = $step->duration_ms
                                ?? ($step->started_at && $step->finished_at
                                    ? $step->finished_at->diffInMilliseconds($step->started_at)
                                    : null);
                            $stepStatus = $step->status;
                        @endphp
                        <div
                            data-status="{{ $stepStatus?->value }}"
                            style="padding: 0.75rem 1rem; {{ $loop->parent->first && $loop->first ? '' : 'border-top: 1px solid color-mix(in oklab, currentColor 12%, transparent);' }}"
                        >
                            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem;">
                                <div style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.875rem;">
                                    {{ $operationKey }} <span style="{{ $muted }}">({{ $step->operation_type }})</span>
                                    @if ($multipleAttempts)
                                        <span style="margin-left: 0.5rem; font-family: inherit; font-size: 0.75rem; {{ $muted }}">Attempt {{ $step->attempt_number }}</span>
                                    @endif
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem;">
                                    @if ($stepStatus)
                                        <x-filament::badge :color="$stepStatus->getColor()" size="sm">{{ $stepStatus->getLabel() }}</x-filament::badge>
                                    @endif
                                    @if ($durationMs !== null)
                                        <span style="{{ $muted }}">{{ $durationMs }}ms</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Error panel --}}
                            @if ($step->error_class || $step->error_message)
                                {{-- danger-500 keeps enough contrast on both light and dark panel backgrounds. --}}
                                <div style="margin-top: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.875rem; color: var(--danger-500); background: color-mix(in oklab, var(--danger-500) 8%, transparent);">
                                    @if ($step->error_class)
                                        <span style="font-weight: 600;">{{ $step->error_class }}</span>:
                                    @endif
                                    {{ $step->error_message }}
                                </div>
                                @if ($step->error_trace && $this->isAdminUser())
                                    <details style="margin-top: 0.375rem;">
                                        <summary style="cursor: pointer; font-size: 0.75rem; color: var(--danger-500);">Stack trace</summary>
                                        <pre style="{{ $codeBlock }} background: color-mix(in oklab, var(--danger-500) 10%, transparent); {{ $border }}">{{ $step->error_trace }}</pre>
                                    </details>
                                @endif
                            @endif

                            {{-- Masked I/O --}}
                            <details style="margin-top: 0.5rem; font-size: 0.75rem;">
                                <summary style="cursor: pointer; {{ $muted }}">Input/Output</summary>
                                <pre style="{{ $codeBlock }} background: color-mix(in oklab, currentColor 5%, transparent); {{ $border }}">{{ json_encode(['input' => $step->masked_input, 'output' => $step->masked_output], JSON_PRETTY_PRINT) }}</pre>
                            </details>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
