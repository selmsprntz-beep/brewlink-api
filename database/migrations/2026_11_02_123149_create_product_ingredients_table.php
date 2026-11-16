<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);                    // kada 1 ka Tall / regular
            $table->decimal('grande_quantity', 10, 2)->nullable(); // kada 1 ka Grande
            $table->timestamps();

            $table->unique(['product_id', 'inventory_item_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('inventory_deducted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('inventory_deducted_at');
        });

        Schema::dropIfExists('product_ingredients');
    }
};