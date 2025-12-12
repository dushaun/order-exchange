<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 10);
            $table->decimal('amount', 16, 8)->default(0);
            $table->decimal('locked_amount', 16, 8)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'symbol']);
        });

        DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_amount_non_negative CHECK (amount >= 0)');
        DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_locked_amount_non_negative CHECK (locked_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
