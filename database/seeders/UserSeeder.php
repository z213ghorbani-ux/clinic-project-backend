<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('12345678'); // رمز عبور پیش‌فرض برای همه کاربران تستی

        // ==========================================
        // ۱. سطح ادمین‌ها (مدیران سیستم - دسترسی کامل)
        // ==========================================
        $admins = [
            [
                'name' => 'علی حسینی (مدیر کل)',
                'username' => 'admin_ali',
                'email' => 'ali.admin@clinic.local',
                'mobile' => '09121110001',
            ],
            [
                'name' => 'رضا محمدی (مدیر فنی)',
                'username' => 'admin_reza',
                'email' => 'reza.admin@clinic.local',
                'mobile' => '09121110002',
            ],
            [
                'name' => 'سارا احمدی (سوپروایزر کلینیک)',
                'username' => 'admin_sara',
                'email' => 'sara.admin@clinic.local',
                'mobile' => '09121110003',
            ],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['username' => $admin['username']],
                array_merge($admin, [
                    'password' => $defaultPassword,
                    'role' => 'admin',
                    'is_active' => true,
                ])
            );
        }

        // ==========================================
        // ۲. کارمندان سطح ۱ (فقط ثبت و ویرایش - بدون حذف و بدون صدور لینک)
        // ==========================================
        $staffLevel1 = [
            [
                'name' => 'مریم کاظمی (پذیرش شیفت صبح)',
                'username' => 'staff1_maryam',
                'email' => 'maryam.staff@clinic.local',
                'mobile' => '09122220001',
            ],
            [
                'name' => 'حسین مرادی (پذیرش شیفت عصر)',
                'username' => 'staff1_hossein',
                'email' => 'hossein.staff@clinic.local',
                'mobile' => '09122220002',
            ],
            [
                'name' => 'زهرا ابراهیمی (منشی بخش)',
                'username' => 'staff1_zahra',
                'email' => 'zahra.staff@clinic.local',
                'mobile' => '09122220003',
            ],
        ];

        foreach ($staffLevel1 as $staff) {
            User::updateOrCreate(
                ['username' => $staff['username']],
                array_merge($staff, [
                    'password' => $defaultPassword,
                    'role' => 'staff_level_1',
                    'is_active' => true,
                ])
            );
        }

        // ==========================================
        // ۳. کارمندان سطح ۲ (ثبت، ویرایش و صدور لینک - بدون حذف)
        // ==========================================
        $staffLevel2 = [
            [
                'name' => 'مهدی کریمی (مسئول پذیرش و صدور قبض)',
                'username' => 'staff2_mehdi',
                'email' => 'mehdi.staff@clinic.local',
                'mobile' => '09123330001',
            ],
            [
                'name' => 'فاطمه نوری (مسئول پرونده‌ها و ارسال پیامک)',
                'username' => 'staff2_fatemeh',
                'email' => 'fatemeh.staff@clinic.local',
                'mobile' => '09123330002',
            ],
            [
                'name' => 'امیرحسین رضایی (کارشناس اسناد پزشکی)',
                'username' => 'staff2_amir',
                'email' => 'amir.staff@clinic.local',
                'mobile' => '09123330003',
            ],
        ];

        foreach ($staffLevel2 as $staff) {
            User::updateOrCreate(
                ['username' => $staff['username']],
                array_merge($staff, [
                    'password' => $defaultPassword,
                    'role' => 'staff_level_2',
                    'is_active' => true,
                ])
            );
        }
    }
}
