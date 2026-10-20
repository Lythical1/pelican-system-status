<?php

namespace Lythical\SystemStatus\Filament\Admin\Widgets;

use App\Models\Node;
use Lythical\SystemStatus\Filament\Admin\Pages\SystemStatus;
use Filament\Widgets\ChartWidget;

class LoadChart extends ChartWidget
{
    protected string $view = 'system-status::load-chart';

    protected ?string $pollingInterval = '10s';

    protected ?string $maxHeight = '150px';

    // null = panel server
    public ?int $nodeId = null;

    protected function getData(): array
    {
        $points = SystemStatus::history($this->nodeId === null ? 'panel' : "node.$this->nodeId", $this->load());

        return [
            'datasets' => [
                [
                    'label' => 'Load',
                    'data' => array_column($points, 1),
                    'tension' => '0.3',
                    'fill' => true,
                ],
            ],
            'labels' => array_column($points, 0),
            'locale' => user()->language ?? 'en',
        ];
    }

    // bug chart for the panel showed up in main dashboard
    public static function canView(): bool
    {
        return !request()->routeIs('filament.admin.pages.dashboard');
    }

    // null when wings is down
    private function load(): ?float
    {
        if ($this->nodeId === null) {
            return sys_getloadavg()[0] ?? null;
        }

        $stats = Node::query()->find($this->nodeId)?->statistics();

        return ($stats['memory_total'] ?? 0) > 0 ? (float) $stats['load_average1'] : null;
    }

    protected function getType(): string
    {
        return 'line';
    }

    // same as ServerCpuChart, plus tooltip without hitting the dot exactly
    protected function getOptions(): array
    {
        return [
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'scales' => [
                'y' => [
                    'min' => 0,
                ],
                'x' => [
                    'display' => false,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
