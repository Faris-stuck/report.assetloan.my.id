<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')) {
            return;
        }

        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException(
                'Test suite wajib menggunakan SQLite.'
            );
        }

        if (
            config('database.connections.sqlite.database')
            !== ':memory:'
        ) {
            throw new RuntimeException(
                'Test suite wajib menggunakan SQLite :memory:.'
            );
        }

        config([
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);

        if (! Schema::hasTable('migrations')) {
            Artisan::call('migrate', [
                '--force' => true,
            ]);
        }
    }
}
