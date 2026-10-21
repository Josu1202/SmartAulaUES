<?php

namespace Tests\Infrastructure;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DockerEnvironmentTest extends TestCase
{
    public function test_docker_http_server_preserves_compose_database_address(): void
    {
        $root = dirname(__DIR__, 2);
        $files = new Filesystem;
        $fixture = sys_get_temp_dir().'/siaa-http-env-'.bin2hex(random_bytes(8));
        $process = null;

        try {
            foreach (['bootstrap/cache', 'public', 'storage/logs', 'storage/framework/views'] as $directory) {
                $files->ensureDirectoryExists($fixture.'/'.$directory);
            }
            $files->copyDirectory($root.'/config', $fixture.'/config');
            $files->copy($root.'/composer.json', $fixture.'/composer.json');
            $files->put($fixture.'/bootstrap/providers.php', '<?php return [];');
            $vendor = str_replace('\\', '/', $root.'/vendor');
            $files->put($fixture.'/.env', "APP_ENV=local\nAPP_DEBUG=false\nLOG_CHANNEL=stderr\nCOMPOSER_VENDOR_DIR=\"{$vendor}\"\nDB_CONNECTION=pgsql\nDB_HOST=127.0.0.1\nDB_PORT=55432\nDB_DATABASE=siaa_test\n");
            $autoload = var_export($root.'/vendor/autoload.php', true);
            $files->put($fixture.'/artisan', "<?php\ndefine('LARAVEL_START', microtime(true));\nrequire {$autoload};\n\$app = require __DIR__.'/bootstrap/app.php';\nexit(\$app->handleCommand(new Symfony\\Component\\Console\\Input\\ArgvInput));\n");
            $files->put($fixture.'/public/index.php', "<?php\ndefine('LARAVEL_START', microtime(true));\nrequire {$autoload};\n\$app = require __DIR__.'/../bootstrap/app.php';\n\$app->handleRequest(Illuminate\\Http\\Request::capture());\n");
            $files->put($fixture.'/bootstrap/app.php', <<<'PHP'
<?php
return Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
    ->withRouting(using: function () {
        Illuminate\Support\Facades\Route::get('/probe', function () {
            return response()->json([
                'host' => config('database.connections.pgsql.host'),
                'port' => (string) config('database.connections.pgsql.port'),
                'database' => config('database.connections.pgsql.database'),
            ]);
        });
    })
    ->withMiddleware()
    ->withExceptions()
    ->create();
PHP);

            preg_match('/^CMD (.+)$/m', $files->get($root.'/Dockerfile'), $matches);
            $command = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
            $arguments = array_values(array_filter(array_slice($command, 2), fn ($argument) => ! str_starts_with($argument, '--host=') && ! str_starts_with($argument, '--port=')));
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $port = substr($address, strrpos($address, ':') + 1);
            $process = new Process([PHP_BINARY, $fixture.'/artisan', ...$arguments, '--host=127.0.0.1', '--port='.$port, '--tries=1'], $fixture, [
                'APP_ENV' => 'local',
                'DB_HOST' => 'db',
                'DB_PORT' => '5432',
                'DB_DATABASE' => 'siaa_test',
                'COMPOSER_VENDOR_DIR' => $root.'/vendor',
                'APP_CONFIG_CACHE' => false,
                'APP_ROUTES_CACHE' => false,
                'APP_SERVICES_CACHE' => false,
                'APP_PACKAGES_CACHE' => false,
            ]);
            $process->start();
            // Cold PHP bootstrap over a Windows Docker bind mount was measured at 14 seconds.
            // Allow startup within a bounded deadline and assert the actual HTTP configuration.
            $client = new Client(['timeout' => 30, 'connect_timeout' => 1]);
            $body = false;
            $httpError = '';
            $deadline = microtime(true) + 45;
            while (microtime(true) < $deadline && $process->isRunning()) {
                try {
                    $body = (string) $client->get('http://127.0.0.1:'.$port.'/probe')->getBody();
                    break;
                } catch (TransferException $exception) {
                    // A connection refused while the child starts is a retry, not a PHP warning.
                    $httpError = $exception->getMessage();
                }
                usleep(100000);
            }

            $this->assertNotFalse($body, $process->getOutput().$process->getErrorOutput().$httpError);
            $this->assertSame(['host' => 'db', 'port' => '5432', 'database' => 'siaa_test'], json_decode($body, true, flags: JSON_THROW_ON_ERROR));
        } finally {
            $process?->stop(1);
            $files->deleteDirectory($fixture);
        }
    }
}
