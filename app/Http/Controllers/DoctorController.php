<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class DoctorController extends Controller
{
    /**
     * نمایش لیست پزشکان (با قابلیت جستجو و صفحه‌بندی)
     */
    public function index(Request $request)
    {
        try {
            $query = Doctor::query();

            // فیلتر جستجو
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('specialty', 'like', "%{$search}%")
                        ->orWhere('medical_council_code', 'like', "%{$search}%");

                    // اگر ستون medical_code هم در جدول وجود داشت روی آن هم جستجو شود
                    if (Schema::hasColumn('doctors', 'medical_code')) {
                        $q->orWhere('medical_code', 'like', "%{$search}%");
                    }
                });
            }

            // فیلتر وضعیت فعال/غیرفعال
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // اگر درخواست صفحه‌بندی داشت، paginate می‌شود در غیر این صورت کل لیست برمی‌گردد
            if ($request->has('page') || $request->has('paginate')) {
                $doctors = $query->latest()->paginate(10);
            } else {
                $doctors = $query->latest()->get();
            }

            return response()->json([
                'status' => 'success',
                'data' => $doctors,
                'doctors' => $doctors
            ], 200);
        } catch (Exception $e) {
            Log::error('Doctor index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در دریافت لیست پزشکان: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ثبت پزشک جدید به همراه آپلود مهر
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'                 => 'required|string|max:255',
                'specialty'            => 'nullable|string|max:255',
                'medical_code'         => 'nullable|string|max:100',
                'medical_council_code' => 'nullable|string|max:100',
                'mobile'               => 'nullable|string|max:20',
                'stamp'                => 'nullable|file|mimes:jpeg,png,jpg,webp|max:4096',
                'signature'            => 'nullable|file|mimes:jpeg,png,jpg,webp|max:4096',
            ]);

            // مدیریت آپلود فایل مهر
            $stampPath = null;
            $file = $request->file('stamp')
                ?? $request->file('signature')
                ?? $request->file('stamp_path')
                ?? $request->file('stamp_image');

            if ($file) {
                $path = $file->store('doctors/stamps', 'public');
                $stampPath = $path; // به جای '/storage/' . $path
            }

            // استخراج کد نظام پزشکی از هر کلیدی که فرانت ارسال کند
            $councilCode = $request->input('medical_council_code')
                ?? $request->input('medical_code')
                ?? $request->input('medical_number')
                ?? '';

            // آماده‌سازی داده‌ها برای ایجاد در دیتابیس
            $dataToCreate = [
                'name'                 => $request->input('name'),
                'specialty'            => $request->input('specialty'),
                'medical_council_code' => $councilCode,
                'stamp_path'           => $stampPath,
                'mobile'               => $request->input('mobile'),
                'is_active'            => $request->boolean('is_active', true),
            ];

            // در صورتی که ستون medical_code در دیتابیس تعریف شده باشد
            if (Schema::hasColumn('doctors', 'medical_code')) {
                $dataToCreate['medical_code'] = $councilCode;
            }

            $doctor = Doctor::create($dataToCreate);

            return response()->json([
                'success' => true,
                'message' => 'پزشک با موفقیت ثبت شد.',
                'doctor'  => $doctor,
                'data'    => $doctor
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'داده‌های ارسالی نامعتبر است.',
                'errors'  => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Doctor store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت پزشک: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * نمایش مشخصات یک پزشک
     */
    public function show($id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return response()->json([
                'status'  => 'error',
                'message' => 'پزشک یافت نشد.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $doctor,
            'doctor' => $doctor
        ], 200);
    }

    /**
     * ویرایش مشخصات پزشک
     */
    public function update(Request $request, $id)
    {
        try {
            $doctor = Doctor::find($id);

            if (!$doctor) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'پزشک مورد نظر یافت نشد.'
                ], 404);
            }

            $validated = $request->validate([
                'name'                 => 'sometimes|required|string|max:255',
                'specialty'            => 'nullable|string|max:255',
                'medical_code'         => 'nullable|string|max:100',
                'medical_council_code' => 'nullable|string|max:100',
                'mobile'               => 'nullable|string|max:20',
                'stamp'                => 'nullable|file|mimes:jpeg,png,jpg,webp|max:4096',
                'is_active'            => 'nullable|boolean',
            ]);

            // مدیریت جایگزینی فایل مهر در صورت آپلود فایل جدید
            $file = $request->file('stamp')
                ?? $request->file('signature')
                ?? $request->file('stamp_path');

            if ($file) {
                if (!empty($doctor->stamp_path)) {
                    $oldPath = str_replace('/storage/', '', $doctor->stamp_path);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                $path = $file->store('doctors/stamps', 'public');
                $validated['stamp_path'] = $path; // به جای '/storage/' . $path
            }

            // همگام‌سازی کد نظام پزشکی
            $councilCode = $request->input('medical_council_code')
                ?? $request->input('medical_code')
                ?? $request->input('medical_number');

            if ($councilCode !== null) {
                $validated['medical_council_code'] = $councilCode;
                if (Schema::hasColumn('doctors', 'medical_code')) {
                    $validated['medical_code'] = $councilCode;
                }
            }

            $doctor->update($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'مشخصات پزشک با موفقیت به‌روزرسانی شد.',
                'doctor'  => $doctor,
                'data'    => $doctor
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'داده‌های ارسالی نامعتبر است.',
                'errors'  => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Doctor update error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'خطا در به‌روزرسانی مشخصات پزشک: ' . $e->getMessage()
            ], 500);
        }
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

            // ۱. حذف فایل مهر از حافظه در صورت وجود
            if (!empty($doctor->stamp_path)) {
                $relativePath = str_replace('/storage/', '', $doctor->stamp_path);
                if (Storage::disk('public')->exists($relativePath)) {
                    Storage::disk('public')->delete($relativePath);
                }
            }

            // ۲. حذف رکورد از دیتابیس
            $doctor->delete();

            return response()->json([
                'success' => true,
                'message' => 'پزشک با موفقیت حذف شد.'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            // جلوگیری از کرش در صورت داشتن ارتباط کلید خارجی (مثلاً نوبت یا فاکتور ثبت شده)
            return response()->json([
                'success' => false,
                'message' => 'امکان حذف این پزشک به دلیل وجود نوبت یا سوابق ثبت‌شده در سیستم وجود ندارد.'
            ], 400);
        } catch (Exception $e) {
            Log::error('Doctor destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف پزشک: ' . $e->getMessage()
            ], 500);
        }
    }
}
