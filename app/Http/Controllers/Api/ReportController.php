<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Archive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Archive::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('file_number', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('issued_by_name', 'like', "%{$search}%")
                    ->orWhere('form_data', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('issued_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('issued_at', '<=', $request->to_date);
        }

        $archives = $query->latest('id')->paginate($perPage);

        $archives->getCollection()->transform(function ($archive) {
            $formData = is_array($archive->form_data) ? $archive->form_data : [];
            $attachments = is_array($archive->attachments) ? $archive->attachments : [];

            // استخراج اطلاعات بیمار از چند ساختار رایج form_data
            $patientData = $formData['patient'] ?? [];

            $patientName = $archive->patient_name
                ?? ($patientData['full_name'] ?? null)
                ?? ($formData['patient_name'] ?? null)
                ?? ($formData['full_name'] ?? null)
                ?? ($formData['name'] ?? null);

            $nationalCode = $archive->national_code
                ?? ($patientData['national_code'] ?? null)
                ?? ($formData['national_code'] ?? null);

            $patientFileNumber = $archive->file_number
                ?? ($patientData['file_number'] ?? null);

            $patientMobile = $archive->mobile
                ?? ($patientData['mobile'] ?? null);

            $services = $formData['services'] ?? [];

            return [
                'id' => $archive->id,

                // ساختار قدیمی که فرانت‌اند استفاده می‌کند
                'patient' => [
                    'id' => $patientData['id'] ?? null,
                    'full_name' => $patientName,
                    'mobile' => $patientMobile,
                    'national_code' => $nationalCode,
                    'file_number' => $patientFileNumber,
                ],

                // کلیدهای تخت (برای سازگاری)
                'patient_name' => $patientName,
                'national_code' => $nationalCode,
                'file_number' => $patientFileNumber,
                'mobile' => $patientMobile,

                'doctor' => [
                    'id' => $archive->issued_by,
                    'name' => $archive->issued_by_name,
                ],

                'services' => $services,
                'form_data' => $formData,
                'attachments' => $attachments,

                'start_at' => $archive->issued_at,
                'issued_at' => $archive->issued_at,
                'created_at' => $archive->created_at,
                'status' => 'completed',
                'invoice' => null,
                'audit_logs' => [],
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $archives,
        ]);
    }


    public function batchDelete(Request $request)
    {
        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date'],
        ]);

        DB::beginTransaction();

        try {
            $query = Archive::whereDate(
                'created_at',
                '>=',
                $validated['from_date']
            )->whereDate(
                'created_at',
                '<=',
                $validated['to_date']
            );

            $count = $query->count();

            if ($count === 0) {
                DB::rollBack();

                return response()->json([
                    'status' => 'warning',
                    'message' => 'هیچ رکوردی در این بازه زمانی یافت نشد.',
                ], 404);
            }

            $query->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "تعداد {$count} رکورد حذف شد.",
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }
}
