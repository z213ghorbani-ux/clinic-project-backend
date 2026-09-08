<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // روابط
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            // مبالغ مالی (به ریال/تومان - عدد صحیح بزرگ)
            $table->unsignedBigInteger('amount'); // مبلغ پایه خدمت
            $table->unsignedBigInteger('discount')->default(0); // مبلغ تخفیف
            $table->unsignedBigInteger('final_amount'); // مبلغ قابل پرداخت (amount - discount)

            // وضعیت و جزئیات پرداخت
            $table->enum('status', ['unpaid', 'paid', 'cancelled'])->default('unpaid');
            $table->enum('payment_method', ['cash', 'pos', 'online', 'card_to_card'])->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
