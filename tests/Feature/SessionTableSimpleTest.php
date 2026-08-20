<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionTableSimpleTest extends TestCase
{
    /**
     * Test: Sessions table exists in database
     * 
     * Bug Condition: Session table missing causes login to fail
     * Validates: Requirements 1.1, 1.2
     * 
     */
    public function test_sessions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
    }
}
