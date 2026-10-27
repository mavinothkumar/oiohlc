<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new App\Http\Controllers\OptionsBreadthMonitorController();
$today = date('Y-m-d');
echo "Testing for date: $today\n";

$req = Illuminate\Http\Request::create('/api/options-breadth-monitor/data', 'GET', ['date' => $today, 'range' => 5]);
$res = $controller->getData($req);
$content = $res->getContent();
$data = json_decode($content, true);

echo "Success: " . ($data['success'] ? 'true' : 'false') . PHP_EOL;
if (!$data['success']) {
    echo "Message: " . ($data['message'] ?? 'none') . PHP_EOL;
} else {
    echo "Timestamps count: " . count($data['timestamps'] ?? []) . PHP_EOL;
    echo "Last timestamp: " . end($data['timestamps']) . PHP_EOL;
    echo "Candles count: " . count($data['lw_chart_candles'] ?? []) . PHP_EOL;
    echo "Is today: " . ($data['is_today'] ? 'true' : 'false') . PHP_EOL;
    echo "Is live: " . ($data['is_live'] ? 'true' : 'false') . PHP_EOL;
}
