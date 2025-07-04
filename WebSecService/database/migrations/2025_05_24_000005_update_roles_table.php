<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'management_level')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->enum('management_level', ['low', 'middle', 'high'])
                    ->nullable()
                    ->comment('Role management level: low (customer tasks), middle (+ low management), high (full access)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'management_level')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('management_level');
            });
        }
    }
}; 