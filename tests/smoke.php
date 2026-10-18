<?php

// php artisan tinker plugins/system-status/tests/smoke.php (fake wings, rolled back)

use App\Models\Node;
use Lythical\SystemStatus\Filament\Admin\Pages\SystemStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$fails = 0;
$check = function (string $label, bool $ok) use (&$fails) {
    echo ($ok ? 'ok   ' : 'FAIL ') . $label . PHP_EOL;
    $fails += $ok ? 0 : 1;
};

DB::beginTransaction();

try {
    cache()->flush(); // use CACHE_STORE=array

    $node = fn (string $name, bool $maintenance = false) => Node::factory()->create([
        'name' => $name, 'fqdn' => "$name.test", 'public' => true, 'maintenance_mode' => $maintenance,
        'memory' => 16384, 'disk' => 102400, 'cpu' => 800, 'tags' => [],
    ]);
    $node('smoke-up');
    $node('smoke-busy', true);
    $node('smoke-down');

    // host => [cpu %, mem GiB], unlisted = down
    $gib = 1024 ** 3;
    $answering = ['smoke-up.test' => [20, 4], 'smoke-busy.test' => [95, 15]];
    Http::fake(function (Request $request) use ($answering, $gib) {
        [$cpu, $mem] = $answering[parse_url($request->url(), PHP_URL_HOST)] ?? [null, null];
        if ($cpu === null) {
            return Http::failedConnection();
        }

        return str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/utilization')
            ? Http::response([
                'memory_total' => 16 * $gib, 'memory_used' => $mem * $gib, 'swap_total' => 0, 'swap_used' => 0,
                'load_average1' => 1.5, 'load_average5' => 1.2, 'load_average15' => 1.0, 'cpu_percent' => $cpu,
                'disk_total' => 100 * $gib, 'disk_used' => 40 * $gib,
            ])
            // pelican wants the token id in the User-Agent
            : Http::response(['architecture' => 'amd64', 'cpu_count' => 8, 'kernel_version' => '6.1', 'os' => 'linux', 'version' => '1.0.0'], 200,
                ['User-Agent' => 'Pelican Wings/v1.0.0 (id:' . Node::query()->where('fqdn', parse_url($request->url(), PHP_URL_HOST))->value('daemon_token_id') . ')']);
    });

    $nodes = collect(SystemStatus::nodes())->keyBy('name');

    $check('online node', $nodes['smoke-up']['online'] && $nodes['smoke-up']['deploy'] && $nodes['smoke-up']['error'] === null);
    $check('bars, no swap', array_column($nodes['smoke-up']['meters'], 'label') === ['CPU', 'Memory', 'Disk']);
    $check('memory 25%', $nodes['smoke-up']['meters'][1]['pct'] === 25.0);
    $check('memory allocated', $nodes['smoke-up']['meters'][1]['alloc'] !== null);
    $check('load and facts', $nodes['smoke-up']['load'] == [1.5, 1.2, 1.0] && !isset($nodes['smoke-up']['facts']['Threads']) && $nodes['smoke-up']['facts']['OS'] === 'linux / amd64' && $nodes['smoke-up']['cores'] === 8);
    $check('maintenance', $nodes['smoke-busy']['maintenance'] && !$nodes['smoke-busy']['deploy']);
    $check('95% cpu critical', $nodes['smoke-busy']['meters'][0]['tone'] === 'critical');
    $check('offline node', !$nodes['smoke-down']['online'] && !$nodes['smoke-down']['deploy']
        && !str_contains($nodes['smoke-down']['error'], 'cURL error')
        && array_column($nodes['smoke-down']['meters'], 'label') === ['Memory allocated', 'Disk allocated']);

    $check('tones', [SystemStatus::tone(69.9), SystemStatus::tone(70), SystemStatus::tone(90)] === ['good', 'high', 'critical']);
    $now = time();
    cache()->put('system_status.load.smoke', [['08:00:00', 9.0, 1, $now - 3600], ['09:59:55', 3.0, 2, $now - 5]]);
    $dots = SystemStatus::history('smoke', 4.0);
    $check('old dot dropped, reading joins last dot', count($dots) === 1 && $dots[0][1] === round(7 / 3, 2));
    cache()->put('system_status.load.smoke', [['09:59:20', 3.0, 3, $now - 40]]);
    $dots = SystemStatus::history('smoke', 1.0);
    $check('new dot after 10s', array_column($dots, 1) === [1.0, 1.0] && $dots[1][2] === $now);
    $check('no reading, no dot', SystemStatus::history('smoke', null) === $dots);
    cache()->put('system_status.load.smoke', array_map(fn (int $i) => ['x', 1.0, 1, $now - 880 + $i * 8], range(0, 99)));
    session()->put('system_status.load.panel', [['x', 1.0, 1, $now]]);
    (new SystemStatus())->mount();
    $check('mount clears session', !session()->has('system_status'));
    $check('history not in session', SystemStatus::history('smoke', 1.0) !== [] && !session()->has('system_status'));
    $check('max 90 dots', count(SystemStatus::history('smoke', 1.0)) === 90);
    $check('panel box', count(SystemStatus::panelBox()['meters']) >= 2);
} finally {
    DB::rollBack();
}

echo $fails ? "$fails FAILED" . PHP_EOL : 'all ok' . PHP_EOL;
