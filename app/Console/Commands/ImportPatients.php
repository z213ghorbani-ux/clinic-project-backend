<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ImportPatients extends Command
{
    protected $signature = 'patients:import {file}';
    protected $description = 'Import patients from CSV (142k rows)';

    public function handle()
    {
        $filePath = base_path($this->argument('file'));

        if (!file_exists($filePath)) {
            $this->error("فایل در مسیر یافت نشد: " . $filePath);
            return;
        }

        $this->info("عملیات شروع شد. لطفا منتظر بمانید...");

        // استفاده از LazyCollection برای حافظه بهینه
        LazyCollection::make(function () use ($filePath) {
            $handle = fopen($filePath, 'r');
            // رد کردن سطر اول (هدر)
            fgetcsv($handle);

            while (($line = fgetcsv($handle, 2000, ",")) !== false) {
                yield $line;
            }
            fclose($handle);
        })->chunk(1000)->each(function ($chunk) {
            $insertData = [];

            foreach ($chunk as $row) {
                // پاکسازی کد ملی
                $nationalCode = ($row[3] === 'NaN' || trim($row[3]) === '') ? null : trim($row[3]);

                // ترکیب نام و نام خانوادگی
                // ترکیب نام و نام خانوادگی و برش تا ۱۴۰ کاراکتر (برای جلوگیری از خطای Data too long)
                $fullName = trim(($row[0] ?? '') . ' ' . ($row[1] ?? ''));
                $fullName = mb_substr($fullName, 0, 140);

                // پاکسازی موبایل: فقط رقم‌ها + جداکننده، سپس گرفتن شماره اول
                $rawMobile = $row[4] ?? '';
                $rawMobile = str_replace(['NaN', 'nan'], '', $rawMobile);
                // اولین شماره قبل از هر جداکننده (- / , / /)
                $firstPhone = preg_split('/[-,\/]/', trim($rawMobile))[0] ?? '';
                // فقط رقم‌ها را نگه دار
                $mobile = preg_replace('/\D/', '', $firstPhone);
                // اگر بین ۴ تا ۱۵ رقم نبود، نامعتبر است → NULL
                if (strlen($mobile) < 4 || strlen($mobile) > 15) {
                    $mobile = null;
                }


                $insertData[] = [
                    'file_number'   => $row[2] ?? null,
                    'national_code' => $nationalCode,
                    'full_name'     => $fullName,
                    'mobile'        => $mobile,
                    'gender'        => null,
                    'birth_date'    => null,
                    'notes'         => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }


            // درج دسته‌ای (Batch Insert) برای سرعت بالا
            DB::table('patients')->insert($insertData);
            $this->line("1000 رکورد دیگر با موفقیت ثبت شد...");
        });

        $this->info("تبریک! تمام ۱۴۲ هزار رکورد وارد شد.");
    }
}
