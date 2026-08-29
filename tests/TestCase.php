<?php

declare(strict_types=1);

namespace Gowa\Laravel\Tests;

use Gowa\Laravel\GowaServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [GowaServiceProvider::class];
    }
}
