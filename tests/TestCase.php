<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature routes are now gated by the granular `permission:` middleware,
     * backed by real Role/Permission rows — not just the legacy `role` string.
     * Every test using RefreshDatabase needs those rows to exist so that
     * User::booted()'s role_user auto-sync has something to attach to and
     * hasPermission() checks resolve correctly, without every test file having
     * to remember to seed it individually.
     *
     * RefreshDatabase only runs `migrate:fresh` (and therefore this seeder)
     * once per test run — every test after that gets a rolled-back transaction
     * on top of this same seeded base state, so it's cheap and consistent.
     */
    protected $seed = true;

    protected $seeder = RolePermissionSeeder::class;
}
