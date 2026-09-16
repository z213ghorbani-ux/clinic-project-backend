<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DoctorController extends Controller
{
    /**
     * نمایش لیست پزشکان
     */
    public function index(Request $request)
    {
        $query = Doctor::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('medical_council_code', 'like', "%{$search}%")
                    ->orWhere('specialty', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $doctors = $query->latest()->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $doctors
        ]);
    }

    /**
     * ثبت پزشک جدید به همراه آپلود مهر
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'medical_council_code' => 'required|string|max:50|unique:doctors,medical_council_code',
            'specialty' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'stamp' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // حداکثر ۲ مگابایت
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('stamp')) {
            $path = $request->file('stamp')->store('doctors/stamps', 'public');
            $validated['stamp_path'] = $path;
        }

        $doctor = Doctor::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'پزشک با موفقیت ثبت شد.',
            'data' => $doctor
        ], 201);
    }

    /**
     * نمایش مشخصات یک پزشک
     */
    public function show(Doctor $doctor)
    {
        return response()->json([
            'status' => 'success',
            'data' => $doctor
        ]);
    }

    /**
     * ویرایش مشخصات پزشک
     */
    public function update(Request $request, Doctor $doctor)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'medical_council_code' => 'sometimes|required|string|max:50|unique:doctors,medical_council_code,' . $doctor->id,
            'specialty' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'stamp' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('stamp')) {
            // حذف مهر قبلی در صورت وجود
            if ($doctor->stamp_path && Storage::disk('public')->exists($doctor->stamp_path)) {
                Storage::disk('public')->delete($doctor->stamp_path);
            }
            $path = $request->file('stamp')->store('doctors/stamps', 'public');
            $validated['stamp_path'] = $path;
        }

        $doctor->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'مشخصات پزشک با موفقیت به‌روزرسانی شد.',
            'data' => $doctor
        ]);
    }

    /**
     * حذف پزشک
     */
    public function destroy($id)
    {
        try {
            $doctor = Doctor::find($id);

            if (!$doctor) {
                return response()->json([
                    'success' => false,
                    'message' => 'پزشک مورد نظر یافت نشد.'
                ], 404);
            }

            DB::beginTransaction();

            // حذف فایل مهر/امضا در صورت وجود
            if (!empty($doctor->stamp_path)) {
                $cleanPath = str_replace('/storage/', '', $doctor->stamp_path);
                if (Storage::disk('public')->exists($cleanPath)) {
                    Storage::disk('public')->delete($cleanPath);
                }
            }

            // حذف رکورد پزشک
            $doctor->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'پزشک با موفقیت حذف شد.'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // اگر به خاطر کلید خارجی در نوبت‌ها یا بایگانی نتوانست حذف کند
            return response()->json([
                'success' => false,
                'message' => 'امکان حذف این پزشک به دلیل وجود پرونده یا نوبت‌های ثبت‌شده به نام ایشان وجود ندارد.'
            ], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف پزشک: ' . $e->getMessage()
            ], 500);
        }
    }
}
