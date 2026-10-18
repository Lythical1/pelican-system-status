{{-- copy of filament's chart widget view minus the section wrapper --}}
@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Widgets\View\Components\ChartWidgetComponent;

    $type = $this->getType();
@endphp

<div class="fi-wi-chart" wire:poll.{{ $this->getPollingInterval() }}="updateChartData" style="margin: 0 .75rem">
    <div class="fi-wi-chart-image">
        <div
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
            wire:ignore
            data-chart-type="{{ $type }}"
            x-data="chart({
                        cachedData: @js($this->getCachedData()),
                        options: @js($this->getOptions()),
                        type: @js($type),
                    })"
            {{
                (new FilamentComponentAttributeBag)
                    ->color(ChartWidgetComponent::class, $this->getColor())
                    ->class(['fi-wi-chart-frame', 'fi-wi-chart-canvas-ctn', 'fi-wi-chart-frame-no-aspect-ratio'])
            }}
        >
            <canvas x-ref="canvas" aria-hidden="true" style="width: 100%; max-height: {{ $this->getMaxHeight() }}"></canvas>

            <span aria-hidden="true" x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
            <span aria-hidden="true" x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
            <span aria-hidden="true" x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
            <span aria-hidden="true" x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
            <span aria-hidden="true" x-ref="tooltipBackgroundColorElement" class="fi-wi-chart-tooltip-bg-color"></span>
            <span aria-hidden="true" x-ref="tooltipTextColorElement" class="fi-wi-chart-tooltip-text-color"></span>
            <span aria-hidden="true" x-ref="tooltipBorderColorElement" class="fi-wi-chart-tooltip-border-color"></span>
        </div>
    </div>
</div>
