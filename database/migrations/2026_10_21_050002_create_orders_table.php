<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('cafe_table_id')->nullable()->constrained('cafe_tables')->nullOnDelete();
            $table->string('customer_name');
            $table->string('order_type')->default('dine_in');   
            $table->string('status')->default('pending');      
            $table->decimal('total', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('stock_deducted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
