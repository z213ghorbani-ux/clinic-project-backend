<?php

namespace Database\Seeders;

use App\Models\Doctor;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doctors = [
            [
                'name' => 'دکتر علی کاظمی',
                'medical_council_code' => '12345',
                'specialty' => 'پوست، مو و زیبایی',
                'mobile' => '09121111111',
                'stamp_path' => null,
                'is_active' => true,
            ],
            [
                'name' => 'دکتر سارا احمدی',
                'medical_council_code' => '54321',
                'specialty' => 'جراحی عمومی و زیبایی',
                'mobile' => '09122222222',
                'stamp_path' => null,
                'is_active' => true,
            ],
            [
                'name' => 'دکتر رضا رضایی',
                'medical_council_code' => '67890',
                'specialty' => 'تغذیه و رژیم‌درمانی',
                'mobile' => '09123333333',
                'stamp_path' => null,
                'is_active' => true,
            ],
        ];

        foreach ($doctors as $doctor) {
            Doctor::updateOrCreate(
                ['medical_council_code' => $doctor['medical_council_code']],
                $doctor
            );
        }
    }
}
