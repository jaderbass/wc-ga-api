<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Nur hinzufügen, wenn Spalte fehlt
            if (! Schema::hasColumn('products', 'woo_product_id')) {
                $table->unsignedBigInteger('woo_product_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('products', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('products', 'last_sync_status')) {
                $table->string('last_sync_status', 32)->nullable()->after('last_synced_at');
            }
            if (! Schema::hasColumn('products', 'last_sync_error')) {
                $table->text('last_sync_error')->nullable()->after('last_sync_status');
            }
            if (! Schema::hasColumn('products', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable()->after('last_sync_error');
            }
        });

        // Index auf woo_product_id nur anlegen, wenn er fehlt
        if (! $this->indexExists('products', 'products_woo_product_id_index')) {
            DB::statement('CREATE INDEX products_woo_product_id_index ON products (woo_product_id)');
        }
        if (! $this->indexExists('products', 'products_last_sync_status_index')) {
            DB::statement('CREATE INDEX products_last_sync_status_index ON products (last_sync_status)');
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'payload_hash'))     $table->dropColumn('payload_hash');
            if (Schema::hasColumn('products', 'last_sync_error'))  $table->dropColumn('last_sync_error');
            if (Schema::hasColumn('products', 'last_sync_status')) $table->dropColumn('last_sync_status');
            if (Schema::hasColumn('products', 'last_synced_at'))   $table->dropColumn('last_synced_at');
            if (Schema::hasColumn('products', 'woo_product_id'))   $table->dropColumn('woo_product_id');
        });

        if ($this->indexExists('products', 'products_woo_product_id_index')) {
            DB::statement('DROP INDEX products_woo_product_id_index ON products');
        }
        if ($this->indexExists('products', 'products_last_sync_status_index')) {
            DB::statement('DROP INDEX products_last_sync_status_index ON products');
        }
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
