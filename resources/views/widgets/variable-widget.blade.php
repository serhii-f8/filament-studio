{{-- Inline styles + Filament fi-* components only: package views are not compiled into the host panel's Tailwind build. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $interface = $this->config('interface', 'text');
            $inputId = 'studio-variable-'.$this->getId();
        @endphp

        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            @if($this->config('label'))
                {{-- fi-fo-field-label-content picks up the panel's label typography and dark-mode colour. --}}
                <label for="{{ $inputId }}" class="fi-fo-field-label" style="font-size: 0.875rem; font-weight: 500;">
                    <span class="fi-fo-field-label-content">{{ $this->config('label') }}</span>
                </label>
            @endif

            @if($interface === 'text')
                <x-filament::input.wrapper>
                    <x-filament::input :id="$inputId" type="text" wire:model.live.debounce.500ms="inputValue" />
                </x-filament::input.wrapper>
            @elseif($interface === 'number')
                <x-filament::input.wrapper>
                    <x-filament::input :id="$inputId" type="number" wire:model.live.debounce.500ms="inputValue" />
                </x-filament::input.wrapper>
            @elseif($interface === 'date')
                <x-filament::input.wrapper>
                    <x-filament::input :id="$inputId" type="date" wire:model.live="inputValue" />
                </x-filament::input.wrapper>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
