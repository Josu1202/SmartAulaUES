<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase { refreshDatabase as private refreshIsolatedDatabase; }

    public function refreshDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        if ($connection !== 'pgsql' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException('Las pruebas requieren una base PostgreSQL dedicada con sufijo _test.');
        }
        $this->refreshIsolatedDatabase();
    }
}
