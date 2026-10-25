<?php

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\View\Engine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Explicit local measurements: GET requests are read-only; the scheduler applies its
// real eligible transitions. No SQL bindings, credentials or request bodies are recorded.
$started = microtime(true);
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$options = getopt('', ['output:', 'repeat::']);
$database = DB::connection()->getPdo()->query('select current_database()')->fetchColumn();
$readDatabase = DB::connection()->getReadPdo()->query('select current_database()')->fetchColumn();
if (! $app->environment('local', 'testing') || ! in_array($database, ['siaa_test', 'smartaulaues_local'], true) || $database !== $readDatabase) {
    throw new RuntimeException('Only verified local/test databases are permitted.');
}
config(['session.driver' => 'array', 'cache.default' => 'array']);
$queries = [];
DB::listen(function ($query) use (&$queries): void {
    $queries[] = ['sql' => $query->sql, 'ms' => $query->time];
});
$timing = (object) ['depth' => 0, 'viewMs' => 0.0];
$resolver = app('view.engine.resolver');
$original = $resolver->resolve('blade');
$resolver->register('blade', fn () => new class($original, $timing) implements Engine
{
    public function __construct(private $engine, private $timing) {}

    public function get($path, array $data = [])
    {
        $start = microtime(true);
        $this->timing->depth++;
        try {
            return $this->engine->get($path, $data);
        } finally {
            if (--$this->timing->depth === 0) {
                $this->timing->viewMs += (microtime(true) - $start) * 1000;
            }
        }
    }
});
$admin = User::where('role', 'coordinator')->where('active', true)->firstOrFail();
$group = Offering::where('active', true)->whereHas('cycle', fn ($q) => $q->where('status', '!=', 'closed'))->firstOrFail();
$paths = ['/login', '/dashboard', '/admin/students', '/admin/teachers', '/offerings', '/sessions', '/offerings/'.$group->id, '/requests', '/admin/imports', '/admin/nfc-credentials'];
$result = ['recorded_at' => now()->toIso8601String(), 'database' => $database, 'php' => PHP_VERSION,
    'bootstrap_ms' => (microtime(true) - $started) * 1000,
    'volume' => ['users' => User::count(), 'groups' => Offering::count(), 'sessions' => ClassSession::count(), 'attendances' => Attendance::count()],
    'requests' => []];
$kernel = app(Illuminate\Contracts\Http\Kernel::class);
foreach ($paths as $path) {
    for ($i = 0; $i < (int) ($options['repeat'] ?? 5); $i++) {
        Auth::forgetGuards();
        if ($path !== '/login') {
            Auth::guard()->setUser($admin);
        }
        $queries = [];
        $timing->viewMs = 0;
        $request = Request::create($path, 'GET');
        $begin = microtime(true);
        $response = $kernel->handle($request);
        $elapsed = (microtime(true) - $begin) * 1000;
        $result['requests'][] = ['path' => $path, 'iteration' => $i + 1, 'status' => $response->getStatusCode(),
            'server_ms' => $elapsed, 'view_ms' => $timing->viewMs, 'sql_ms' => array_sum(array_column($queries, 'ms')), 'queries' => $queries];
        $kernel->terminate($request, $response);
    }
}
for ($i = 0; $i < (int) ($options['repeat'] ?? 5); $i++) {
    $queries = [];
    $begin = microtime(true);
    $status = Artisan::call('attendance:process-closures');
    $result['scheduler'][] = ['iteration' => $i + 1, 'status' => $status, 'server_ms' => (microtime(true) - $begin) * 1000,
        'sql_ms' => array_sum(array_column($queries, 'ms')), 'queries' => $queries];
}
$output = $options['output'] ?? 'storage/app/private/performance.json';
file_put_contents($output, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo 'Measurements saved: '.count($result['requests']).' requests and '.count($result['scheduler']).' scheduler executions.'.PHP_EOL;
