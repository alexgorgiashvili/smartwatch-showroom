<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

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

        if (!self::$databaseMigrated) {
            $app->make(Kernel::class)->call('migrate', [
                '--force' => true,
            ]);

            RefreshDatabaseState::$migrated = true;
            self::$databaseMigrated = true;
        }

        return $app;
    }
}
