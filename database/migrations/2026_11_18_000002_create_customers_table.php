<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A snapshot of one customer as it was during one scan. The upstream API
 * returns different random customers on every call, so customers are not
 * shared between scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();

            // Every upstream field is nullable: the upstream spec marks none as required.
            $table->unsignedBigInteger('external_id')->nullable();
            $table->unsignedBigInteger('bsn')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->string('tag')->nullable();
            $table->string('street')->nullable();
            $table->string('postcode')->nullable();
            $table->string('city')->nullable();
            $table->json('products');
            $table->string('ip_address')->nullable();
            $table->string('iban')->nullable();
            $table->date('last_invoice_date')->nullable();
            $table->dateTime('last_login_at')->nullable();

            $table->boolean('is_fraudulent');
            $table->json('fraud_reasons');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
