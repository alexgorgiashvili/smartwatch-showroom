<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesApplication
{
    private static bool $databaseMigrated = false;

    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Each new in-memory SQLite connection starts empty, even when a
        // previous test process already completed the migration set.
        $emptySqlite = DB::getDriverName() === 'sqlite'
            && DB::getDatabaseName() === ':memory:'
            && !Schema::hasTable('users');

        if (!self::$databaseMigrated || $emptySqlite) {
            $app->make(Kernel::class)->call('migrate', [
                '--force' => true,
            ]);

            RefreshDatabaseState::$migrated = true;
            self::$databaseMigrated = true;
        }

        return $app;
    }
}
