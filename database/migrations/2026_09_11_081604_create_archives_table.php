<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archives', function (Blueprint $table) {
            $table->id();
            $table->string('patient_name');
            $table->string('national_code');
            $table->string('file_number')->nullable();
            $table->string('mobile')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable(); // شناسه کاربر صدورکننده جواب
            $table->string('issued_by_name')->nullable();        // نام کاربر صدورکننده جهت نمایش آسان در جدول
            $table->date('issued_at');                           // تاریخ صدور جوابدهی
            $table->json('form_data')->nullable();               // تمام مقادیر فرم جوابدهی
            $table->json('attachments')->nullable();             // مسیر فایل‌های اتچ‌شده
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('archives');
    }
};
