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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('file_number', 50)->nullable()->index(); // شماره پرونده کلینیک
            $table->string('national_code', 10)->nullable()->index(); // کد ملی ۱۰ رقمی
            $table->string('full_name', 150)->index(); // نام و نام خانوادگی
            $table->string('mobile', 15)->index(); // شماره تماس برای SMS
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('birth_date', 20)->nullable(); // فرمت شمسی یا میلادی
            $table->text('notes')->nullable(); // یادداشت‌های ضروری
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
