<?php

use Database\Seeders\SupervisiAwalSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(SupervisiAwalSeeder::class)->pasangIzin();
    }

    public function down(): void
    {
        // Preserve pre-existing and administrator-managed role grants on rollback.
    }
};
