<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * T6 (v1.5.0): recommended_tools validates against the tool registry
     * (modality-aware). Production runs ToolLogoSeeder, so every database-
     * backed test gets the same baseline — otherwise any test posting a
     * form with "ChatGPT" would fail validation for a reason unrelated to
     * its subject. No-ops for database-less Unit tests (no table yet).
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (\Illuminate\Support\Facades\Schema::hasTable('tool_logos')) {
            (new \Database\Seeders\ToolLogoSeeder)->run();
        }
    }
}
