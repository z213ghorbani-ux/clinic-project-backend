<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    /**
     * لیست بیماران با قابلیت صفحه‌بندی و جستجوی سریع
     */
    public function index(Request $request)
    {
        $query = Patient::query();

        // جستجوی سریع روی نام، کد ملی، شماره پرونده یا موبایل
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('national_code', 'like', "{$search}%")
                    ->orWhere('file_number', 'like', "{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        // صفحه‌بندی ۲۰تایی برای سرعت بالا
        $patients = $query->latest('id')->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $patients
        ]);
    }

    /**
     * ثبت بیمار جدید
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'nullable|string|max:50',
            'national_code' => 'nullable|string|max:10',
            'full_name' => 'required|string|max:150',
            'mobile' => 'required|string|max:15',
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $patient = Patient::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'پرونده بیمار با موفقیت ایجاد شد.',
            'data' => $patient
        ], 201);
    }

    /**
     * نمایش مشخصات یک بیمار
     */
    public function show(Patient $patient)
    {
        return response()->json([
            'status' => 'success',
            'data' => $patient
        ]);
    }

    /**
     * ویرایش اطلاعات بیمار
     */
    public function update(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'file_number' => 'nullable|string|max:50',
            'national_code' => 'nullable|string|max:10',
            'full_name' => 'sometimes|required|string|max:150',
            'mobile' => 'sometimes|required|string|max:15',
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $patient->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'اطلاعات بیمار به‌روزرسانی شد.',
            'data' => $patient
        ]);
    }

    /**
     * حذف پرونده بیمار
     */
    public function destroy(Patient $patient)
    {
        $patient->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'پرونده بیمار با موفقیت حذف شد.'
        ]);
    }
}
