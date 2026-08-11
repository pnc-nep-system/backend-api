<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `role` column started as a fixed enum('nep_admin','nep_coordinator','member_org').
 * User Management now needs to let admins assign any role that exists in the
 * `roles` table (including custom roles created via Role Management), so the
 * column can no longer be constrained to those three values at the DB level.
 *
 * Uses a raw ALTER TABLE (not Blueprint::change()) because doctrine/dbal isn't
 * installed in this project and pulling it in just for one column-type change
 * isn't worth the added dependency.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY role VARCHAR(255) NOT NULL');
    }

    public function down(): void
    {
        // Reverting requires every existing user's role to be one of the
        // original three values — if a custom role has been assigned to
        // anyone since this migrated forward, this will fail, which is the
        // correct behaviour (silently truncating their role would be worse).
        DB::statement("ALTER TABLE users MODIFY role ENUM('nep_admin','nep_coordinator','member_org') NOT NULL");
    }
};
