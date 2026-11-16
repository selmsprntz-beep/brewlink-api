<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('customer_name');
            $table->string('contact_number');
            $table->string('email')->nullable();
            $table->date('reservation_date');
            $table->string('reservation_time', 5); 
            $table->unsignedSmallInteger('party_size');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
