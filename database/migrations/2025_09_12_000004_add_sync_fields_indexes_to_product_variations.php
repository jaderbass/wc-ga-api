<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Fehlende Spalten ergänzen (idempotent)
        Schema::table('product_variations', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variations', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('product_variations', 'last_sync_status')) {
                $table->string('last_sync_status', 32)->nullable()->after('last_synced_at');
            }
            if (! Schema::hasColumn('product_variations', 'last_sync_error')) {
                $table->text('last_sync_error')->nullable()->after('last_sync_status');
            }
            if (! Schema::hasColumn('product_variations', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable()->after('last_sync_error');
            }
        });

        // 2) Indexe idempotent anlegen
        if (! $this->indexExists('product_variations', 'product_variations_woo_variation_id_index')) {
            DB::statement('CREATE INDEX product_variations_woo_variation_id_index ON product_variations (woo_variation_id)');
        }
        if (! $this->indexExists('product_variations', 'product_variations_last_sync_status_index')) {
            DB::statement('CREATE INDEX product_variations_last_sync_status_index ON product_variations (last_sync_status)');
        }
    }

    public function down(): void
    {
        // Indexe idempotent entfernen
        if ($this->indexExists('product_variations', 'product_variations_woo_variation_id_index')) {
            DB::statement('DROP INDEX product_variations_woo_variation_id_index ON product_variations');
        }
        if ($this->indexExists('product_variations', 'product_variations_last_sync_status_index')) {
            DB::statement('DROP INDEX product_variations_last_sync_status_index ON product_variations');
        }

        // Spalten idempotent entfernen
        Schema::table('product_variations', function (Blueprint $table) {
            if (Schema::hasColumn('product_variations', 'payload_hash')) {
                $table->dropColumn('payload_hash');
            }
            if (Schema::hasColumn('product_variations', 'last_sync_error')) {
                $table->dropColumn('last_sync_error');
            }
            if (Schema::hasColumn('product_variations', 'last_sync_status')) {
                $table->dropColumn('last_sync_status');
            }
            if (Schema::hasColumn('product_variations', 'last_synced_at')) {
                $table->dropColumn('last_synced_at');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::getDatabaseName();
        $exists = DB::selectOne("
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
            LIMIT 1
        ", [$db, $table, $indexName]);

        return (bool) $exists;
    }
};
