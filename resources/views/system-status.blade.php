@php
    $column = \App\Filament\Components\Tables\Columns\ServerEntryColumn::make('system_status');
    $status = fn (string $tone) => ['good' => 'success', 'high' => 'warning', 'critical' => 'danger'][$tone];
    $dot = fn (bool $online) => \Filament\Support\Facades\FilamentColor::getColor($online ? 'success' : 'danger')[500];
    $bar = fn (float $pct, string $tone, string $label) => [
        'getState' => fn () => $pct,
        'getMaxValue' => fn () => 100,
        'getProgressPercentage' => fn () => min(100, $pct),
        'getProgressLabel' => fn () => $label,
        'getProgressStatus' => fn () => $status($tone),
        'getProgressColor' => fn () => $column->getProgressColorForStatus($status($tone)),
    ];
@endphp

<x-filament-panels::page>
    <style>
        .sys-status .ss-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 22rem), 1fr)); gap: 1.5rem; align-items: start; }
        .sys-status .ss-dot { display: inline-block; width: .625rem; height: .625rem; border-radius: 9999px; margin-right: .5rem; vertical-align: middle; position: relative; top: -1px; }
        .sys-status .ss-resource { margin-bottom: 1rem; }
        .sys-status .ss-resource > .ss-label { display: block; margin: 0 .75rem .375rem; }
        .sys-status .ss-resource .fi-ta-text + .fi-ta-text { margin-top: .5rem; }
        .sys-status .ss-facts { display: grid; grid-template-columns: 1fr 1fr; gap: .25rem 1rem; margin: 0 .75rem; }
        .sys-status .ss-facts dd { margin: 0 0 .5rem; overflow-wrap: anywhere; }
    </style>

    <div class="sys-status" wire:poll.10s>
        <div class="ss-grid">
            @foreach ([['name' => 'Panel', 'online' => true, 'error' => null, 'maintenance' => false, 'deploy' => null] + $box, ...$nodes] as $card)
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="ss-dot" style="background: {{ $dot($card['online']) }}" title="{{ $card['online'] ? 'Online' : 'Offline' }}"></span>{{ $card['name'] }}
                    </x-slot>
                    <x-slot name="description">{{ $card['sub'] }}</x-slot>

                    @if ($card['maintenance'] || $card['deploy'] !== null)
                        <x-slot name="afterHeader">
                            @if ($card['maintenance'])
                                <x-filament::badge color="warning">Maintenance</x-filament::badge>
                            @endif
                            @if ($card['deploy'] !== null)
                                <x-filament::badge :color="$card['deploy'] ? 'success' : 'gray'">{{ $card['deploy'] ? 'Deploy' : 'No deploy' }}</x-filament::badge>
                            @endif
                        </x-slot>
                    @endif

                    @if ($card['error'])
                        <p class="text-sm font-bold" style="color: {{ $dot(false) }}; margin: 0 .75rem 1rem">{{ $card['error'] }}</p>
                    @endif

                    @foreach ($card['meters'] as $i => $meter)
                        <div class="ss-resource">
                            <span class="ss-label text-sm font-medium">{{ $meter['label'] }}</span>
                            @include('livewire.columns.progress-bar-column', $bar($meter['pct'], $meter['tone'], $meter['text']))
                            @if ($meter['alloc'])
                                @include('livewire.columns.progress-bar-column', $bar($meter['alloc']['pct'], $meter['alloc']['tone'], 'allocated ' . $meter['alloc']['text']))
                            @endif
                        </div>

                        @if ($i === 0 && $card['load'])
                            <div class="ss-resource">
                                <span class="ss-label text-sm font-medium">
                                    <span class="text-gray-500 dark:text-gray-400">{{ round($card['load'][0], 2) }}{{ $card['cores'] ? " / {$card['cores']} cores" : '' }}</span>
                                </span>
                                @livewire(\Lythical\SystemStatus\Filament\Admin\Widgets\LoadChart::class, ['nodeId' => $card['id'] ?? null], key('load-' . ($card['id'] ?? 'panel')))
                            </div>
                        @endif
                    @endforeach

                    @if ($card['facts'])
                        <dl class="ss-facts">
                            @foreach ($card['facts'] as $label => $value)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="text-sm font-medium">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
