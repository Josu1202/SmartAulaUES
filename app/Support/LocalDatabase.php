<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LocalDatabase
{
    public static function verify(bool $allowTesting = false): array
    {
        $connection = DB::connection();
        $sql = 'select current_database() as database, inet_server_addr()::text as address, inet_server_port() as port';
        $writeTarget = $connection->getPdo()->query($sql)->fetch(\PDO::FETCH_ASSOC);
        $readTarget = $connection->getReadPdo()->query($sql)->fetch(\PDO::FETCH_ASSOC);
        $write = $writeTarget['database'];
        $read = $readTarget['database'];
        $allowed = app()->environment('local') && $write === 'smartaulaues_local';
        $allowed = $allowed || ($allowTesting && app()->environment('testing') && $write === 'siaa_test');
        if (! $allowed || $writeTarget !== $readTarget || $connection->getDriverName() !== 'pgsql'
            || ! in_array(config('database.connections.pgsql.host'), ['db', '127.0.0.1', 'localhost'], true)
            || config('database.connections.pgsql.database') !== $write) {
            throw new RuntimeException('Operación reservada a la base local desechable verificada. No se modificó la conexión.');
        }

        return ['host' => config('database.connections.pgsql.host'), 'port' => config('database.connections.pgsql.port'),
            'server' => $writeTarget['address'], 'server_port' => $writeTarget['port'], 'read' => $read, 'write' => $write];
    }
}
