<?php

namespace Lythical\SystemStatus;

use Lythical\SystemStatus\Filament\Admin\Pages\SystemStatus;
use Lythical\SystemStatus\Filament\Admin\Widgets\LoadChart;
use Filament\Contracts\Plugin;
use Filament\Panel;

class SystemStatusPlugin implements Plugin
{
    public function getId(): string
    {
        return 'system-status';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'admin') {
            $panel->pages([SystemStatus::class])->widgets([LoadChart::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
