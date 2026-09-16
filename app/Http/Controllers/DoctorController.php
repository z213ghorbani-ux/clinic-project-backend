<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
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

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('medical_code', 'like', "%{$search}%")
                        ->orWhere('medical_council_code', 'like', "%{$search}%")
                        ->orWhere('specialty', 'like', "%{$search}%");
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // اگر پارامتر paginate فرستاده نشده بود، لیست کامل را برمی‌گردانیم تا فرانت دچار مشکل نشود
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
            return response()->json(['message' => 'خطا در دریافت لیست پزشکان'], 500);
        }
    }

    /**
     * ثبت پزشک جدید به همراه آپلود مهر
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'specialty' => 'nullable|string|max:255',
                'medical_code' => 'nullable|string|max:100',
                'stamp' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:4096',
                'signature' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:4096',
            ]);

            $stampPath = null;

            // دریافت فایل مهر از هر کلیدی که فرانت ارسال کرده باشد
            $file = $request->file('stamp')
                ?? $request->file('signature')
                ?? $request->file('stamp_path')
                ?? $request->file('stamp_image');

            if ($file) {
                $path = $file->store('doctors/stamps', 'public');
                $stampPath = '/storage/' . $path;
            }

            $doctor = Doctor::create([
                'name' => $request->input('name'),
                'specialty' => $request->input('specialty'),
                'medical_code' => $request->input('medical_code') ?? $request->input('medical_number'),
                'stamp_path' => $stampPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'پزشک با موفقیت ثبت شد.',
                'doctor' => $doctor,
                'data' => $doctor
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'داده‌های ارسالی نامعتبر است.',
                'errors' => $e->errors()
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
            return response()->json(['message' => 'پزشک یافت نشد.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $doctor,
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
                return response()->json(['message' => 'پزشک مورد نظر یافت نشد.'], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'specialty' => 'nullable|string|max:255',
                'medical_code' => 'nullable|string|max:100',
                'stamp' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:4096',
                'is_active' => 'nullable|boolean',
            ]);

            if ($request->hasFile('stamp')) {
                // حذف مهر قبلی در صورت وجود
                if (!empty($doctor->stamp_path)) {
                    $oldPath = str_replace('/storage/', '', $doctor->stamp_path);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                $path = $request->file('stamp')->store('doctors/stamps', 'public');
                $validated['stamp_path'] = '/storage/' . $path;
            }

            $doctor->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'مشخصات پزشک با موفقیت به‌روزرسانی شد.',
                'data' => $doctor
            ], 200);
        } catch (Exception $e) {
            Log::error('Doctor update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
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

            // ۱. حذف فایل تصویر مهر در صورت وجود
            if (!empty($doctor->stamp_path)) {
                $relativePath = str_replace('/storage/', '', $doctor->stamp_path);
                if (Storage::disk('public')->exists($relativePath)) {
                    Storage::disk('public')->delete($relativePath);
                }
            }

            // ۲. حذف رکورد پزشک
            $doctor->delete();

            return response()->json([
                'success' => true,
                'message' => 'پزشک با موفقیت حذف شد.'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            // جلوگیری از ارور ۵۰۰ در صورت وجود وابستگی (FK)
            return response()->json([
                'success' => false,
                'message' => 'امکان حذف این پزشک به دلیل وجود پرونده یا نوبت‌های ثبت‌شده وجود ندارد.'
            ], 400);
        } catch (Exception $e) {
            Log::error('Doctor destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف: ' . $e->getMessage()
            ], 500);
        }
    }
}
