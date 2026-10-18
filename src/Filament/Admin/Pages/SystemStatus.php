<?php

namespace Lythical\SystemStatus\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use App\Models\Node;
use BackedEnum;
use Filament\Pages\Page;

class SystemStatus extends Page
{
    protected static ?string $slug = 'system-status';

    protected static ?string $title = 'System status';

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::HeartRateMonitor;

    protected string $view = 'system-status::system-status';

    public static function canAccess(): bool
    {
        return user()?->can('viewList node') ?? false;
    }

    public function mount(): void
    {
        session()->forget('system_status');
    }

    protected function getViewData(): array
    {
        return [
            'box' => self::panelBox(),
            'nodes' => self::nodes(),
        ];
    }

    public static function history(string $key, ?float $load): array
    {
        $key = "system_status.load.$key";
        $dots = collect(cache()->get($key, []))
            ->filter(fn ($dot) => count($dot) === 4 && $dot[3] >= time() - 900)
            ->values();

        if ($load !== null) {
            $last = $dots->last();
            if ($last && $last[3] > time() - 10) {
                $dots->put($dots->count() - 1, [$last[0], $last[1] + $load, $last[2] + 1, $last[3]]);
            } else {
                $dots->push([now(user()?->timezone ?? 'UTC')->format('H:i:s'), $load, 1, time()]);
            }
        }

        $dots = $dots->take(-90)->values()->all();
        cache()->put($key, $dots, now()->addMinutes(15));

        return array_map(fn (array $dot) => [$dot[0], round($dot[1] / $dot[2], 2), $dot[3]], $dots);
    }

    public static function panelBox(): array
    {
        $read = fn (string $file) => is_readable($file) ? (string) file_get_contents($file) : '';

        preg_match_all('/^(\w+):\s+(\d+) kB$/m', $read('/proc/meminfo'), $m);
        $mem = array_map(fn ($kb) => (int) $kb * 1024, array_combine($m[1], $m[2]));
        $cpuinfo = $read('/proc/cpuinfo');
        $cores = preg_match_all('/^processor\s*:/m', $cpuinfo) ?: null;
        $load = sys_getloadavg() ?: null;
        $uptime = (int) (float) $read('/proc/uptime') ?: null;
        $diskTotal = @disk_total_space(base_path()) ?: 0;
        $processes = count(glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: []) ?: null;

        $meters = [];
        if ($load && $cores) {
            $meters[] = self::meter('CPU (load per core)', $load[0] / $cores * 100, 100, fn ($v) => round($v) . '%', text: round($load[0] / $cores * 100) . "% of $cores cores");
        }
        if (isset($mem['MemTotal'], $mem['MemAvailable'])) {
            $meters[] = self::meter('Memory', $mem['MemTotal'] - $mem['MemAvailable'], $mem['MemTotal'], self::gib(...));
        }
        if (!empty($mem['SwapTotal'])) {
            $meters[] = self::meter('Swap', $mem['SwapTotal'] - ($mem['SwapFree'] ?? 0), $mem['SwapTotal'], self::gib(...));
        }
        if ($diskTotal) {
            $meters[] = self::meter('Disk', $diskTotal - (@disk_free_space(base_path()) ?: 0), $diskTotal, self::gib(...));
        }

        return [
            'sub' => implode(', ', array_filter([
                gethostname(),
                preg_match('/^PRETTY_NAME="?([^"\n]+)/m', $read('/etc/os-release'), $os) ? $os[1] : PHP_OS_FAMILY,
                'PHP ' . PHP_VERSION,
            ])),
            'meters' => $meters,
            'load' => $load,
            'cores' => $cores,
            'facts' => array_filter([
                'CPU model' => preg_match('/^model name\s*:\s*(.+)$/m', $cpuinfo, $model) ? trim($model[1]) : null,
                'Cores' => $cores,
                'Uptime' => $uptime ? self::duration($uptime) : null,
                'Last reboot' => $uptime ? now()->subSeconds($uptime)->format('Y-m-d H:i') : null,
                'Processes' => $processes,
                'Kernel' => php_uname('r'),
            ]),
        ];
    }

    public static function nodes(): array
    {
        return Node::query()
            ->withCount('servers')
            ->withSum('servers', 'cpu')
            ->withSum('servers', 'memory')
            ->withSum('servers', 'disk')
            ->orderBy('name')
            ->get()
            ->map(function (Node $node) {
                $stats = $node->statistics(); // zeros if wings is down
                $online = $stats['memory_total'] > 0;
                $info = $node->systemInformation();
                $maintenance = $node->isUnderMaintenance();
                $mib = 1024 * 1024;

                $deploy = $online && $node->public && !$maintenance;

                // limit 0 = unlimited, no bar
                $alloc = fn (string $field) => [(int) $node->{"servers_sum_$field"}, (int) $node->{$field}];
                [$cpuAlloc, $cpuCap] = $alloc('cpu');
                [$memAlloc, $memCap] = $alloc('memory');
                [$diskAlloc, $diskCap] = $alloc('disk');

                $meters = $online ? array_filter([
                    self::meter('CPU', $stats['cpu_percent'], 100, fn ($v) => round($v, 1) . '%',
                        alloc: $cpuCap ? [$cpuAlloc, $cpuCap, fn ($v) => "$v%"] : null, text: round($stats['cpu_percent'], 1) . '%'),
                    self::meter('Memory', $stats['memory_used'], $stats['memory_total'], self::gib(...),
                        alloc: $memCap ? [$memAlloc * $mib, $memCap * $mib, self::gib(...)] : null),
                    $stats['swap_total'] ? self::meter('Swap', $stats['swap_used'], $stats['swap_total'], self::gib(...)) : null,
                    self::meter('Disk', $stats['disk_used'], $stats['disk_total'], self::gib(...),
                        alloc: $diskCap ? [$diskAlloc * $mib, $diskCap * $mib, self::gib(...)] : null),
                ]) : array_filter([
                    $memCap ? self::meter('Memory allocated', $memAlloc * $mib, $memCap * $mib, self::gib(...)) : null,
                    $diskCap ? self::meter('Disk allocated', $diskAlloc * $mib, $diskCap * $mib, self::gib(...)) : null,
                ]);

                return [
                    'id' => $node->id,
                    'cores' => $online ? ($info['cpu_count'] ?? null) : null,
                    'name' => $node->name,
                    'sub' => implode(', ', array_filter([
                        "$node->fqdn:$node->daemon_listen",
                        $online ? 'wings ' . ($info['version'] ?? '?') : null,
                        $node->servers_count . ' ' . str('server')->plural($node->servers_count),
                    ])),
                    'online' => $online,
                    // strip curl noise
                    'error' => $online ? null : str($info['exception'] ?? 'Not responding')->replaceMatches('/^cURL error \d+: /', '')->before(' after ')->before(' (see ')->toString(),
                    'maintenance' => $maintenance,
                    'deploy' => $deploy,
                    'meters' => array_values($meters),
                    'load' => $online ? [$stats['load_average1'], $stats['load_average5'], $stats['load_average15']] : null,
                    'facts' => $online ? array_filter([
                        'OS' => isset($info['os']) ? "{$info['os']} / " . ($info['architecture'] ?? '?') : null,
                        'Kernel' => $info['kernel_version'] ?? null,
                    ]) : [],
                ];
            })
            ->all();
    }

    // $alloc = [used, total, formatter]
    public static function meter(string $label, float $used, float $total, callable $fmt, ?array $alloc = null, ?string $text = null): array
    {
        $pct = fn (float $u, float $t) => $t > 0 ? round($u / $t * 100, 1) : 0.0;
        $p = $pct($used, $total);

        return [
            'label' => $label,
            'text' => $text ?? $fmt($used) . ' / ' . $fmt($total) . " ($p%)",
            'pct' => $p,
            'tone' => self::tone($p),
            'alloc' => $alloc ? [
                'text' => $alloc[2]($alloc[0]) . ' / ' . $alloc[2]($alloc[1]) . ' (' . $pct($alloc[0], $alloc[1]) . '%)',
                'pct' => $pct($alloc[0], $alloc[1]),
                'tone' => self::tone($pct($alloc[0], $alloc[1])),
            ] : null,
        ];
    }

    public static function tone(float $pct): string
    {
        return match (true) {
            $pct >= 90 => 'critical',
            $pct >= 70 => 'high',
            default => 'good',
        };
    }

    private static function gib(float $bytes): string
    {
        return round($bytes / 1024 ** 3, 1) . ' GiB';
    }

    private static function duration(int $seconds): string
    {
        return intdiv($seconds, 86400) . 'd ' . intdiv($seconds % 86400, 3600) . 'h ' . intdiv($seconds % 3600, 60) . 'm';
    }
}
