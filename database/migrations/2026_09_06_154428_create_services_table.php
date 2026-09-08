<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اجرای مایگریشن و ایجاد جدول خدمات
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            // نام خدمت
            $table->string('name', 150);

            // مبلغ خدمت به‌صورت عدد صحیح
            $table->unsignedBigInteger('price');

            // مدت‌زمان تقریبی انجام خدمت، برحسب دقیقه
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            // توضیحات تکمیلی خدمت
            $table->text('description')->nullable();

            // وضعیت فعال یا غیرفعال بودن خدمت
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ایندکس‌ها برای افزایش سرعت جست‌وجو و فیلتر
            $table->index('name');
            $table->index('is_active');
        });
    }

    /**
     * بازگردانی مایگریشن و حذف جدول خدمات
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
