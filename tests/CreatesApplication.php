<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        putenv('DB_DATABASE=kp_crm_testing');
        $_ENV['DB_DATABASE'] = 'kp_crm_testing';
        $_SERVER['DB_DATABASE'] = 'kp_crm_testing';

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        config([
            'database.connections.mysql.database' => 'kp_crm_testing',
        ]);

        return $app;
    }
}
