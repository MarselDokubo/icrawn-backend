<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paystack_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('reference')->unique();
            $table->string('authorization_url')->nullable(); // we’ll store it
            $table->string('access_code')->nullable();
            $table->string('status')->nullable();            // initialized/success/failed/mismatch/…
            $table->unsignedBigInteger('amount')->nullable(); // kobo
            $table->unsignedBigInteger('fees')->nullable();   // optional from verify
            $table->json('payload')->nullable();              // raw API payloads
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_payments');
    }
};