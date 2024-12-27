<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHealthDashboardTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('health_dashboard', function (Blueprint $table) {
            $table->id();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('metric', 100);
            $table->text('result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_dashboard');
    }
}