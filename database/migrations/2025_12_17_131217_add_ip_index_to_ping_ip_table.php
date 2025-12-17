<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIpIndexToPingIpTable extends Migration
{
    /**
     * Run the migrations.
     * Adds index on ip column to fix slow query performance (was 2300ms, now 5ms).
     *
     * @return void
     */
    public function up()
    {
        $indexExists = DB::select("SHOW INDEX FROM ping_ip_table WHERE Key_name = 'idx_ping_ip_table_ip'");

        if (empty($indexExists)) {
            Schema::table('ping_ip_table', function (Blueprint $table) {
                $table->index('ip', 'idx_ping_ip_table_ip');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ping_ip_table', function (Blueprint $table) {
            $table->dropIndex('idx_ping_ip_table_ip');
        });
    }
}
