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

            // ۱. استخراج اطلاعات بیمار از داخل updatedQueue
            $queueItem = $formData['invoiceDetails']['updatedQueue'][0] ?? [];
            $patientFromQueue = $queueItem['patient'] ?? [];

            $patientName = $patientFromQueue['full_name']
                ?? $formData['patient_name']
                ?? $formData['patient']['full_name']
                ?? $formData['full_name']
                ?? $formData['name']
                ?? 'نامشخص';

            $nationalCode = $patientFromQueue['national_code']
                ?? $formData['national_code']
                ?? $formData['patient']['national_code']
                ?? $archive->file_number; // کد ملی معمولاً همان شماره پرونده یا مقدار ثبت‌شده است

            $mobile = $patientFromQueue['mobile']
                ?? $archive->mobile
                ?? $formData['mobile']
                ?? '-';

            $fileNumber = $patientFromQueue['file_number']
                ?? $archive->file_number
                ?? '-';

            // ۲. استخراج خدمات ثبت‌شده
            $services = $formData['services'] ?? [];
            if (empty($services) && isset($formData['invoiceDetails']['updatedQueue'])) {
                foreach ($formData['invoiceDetails']['updatedQueue'] as $q) {
                    if (isset($q['services']) && is_array($q['services'])) {
                        foreach ($q['services'] as $s) {
                            $services[] = $s;
                        }
                    }
                }
            }

            // ۳. پزشک معالج
            $doctorData = $queueItem['doctor'] ?? [];
            $doctorName = $archive->issued_by_name
                ?? ($doctorData['name'] ?? null);

            // ۴. اطلاعات فاکتور
            $invoiceDetails = $formData['invoiceDetails'] ?? null;
            $invoice = null;
            if ($invoiceDetails || ($formData['hasInvoice'] ?? false)) {
                $invoice = [
                    'total_price' => $invoiceDetails['totalPrice'] ?? null,
                    'discount' => $invoiceDetails['discount'] ?? 0,
                    'payable_amount' => $invoiceDetails['payableAmount'] ?? null,
                    'is_paid' => true,
                    'payment_method' => 'cash',
                ];
            }

            return [
                'id' => $archive->id,

                // ساختار آبجکت بیمار برای فرانت‌اند
                'patient' => [
                    'id' => $patientFromQueue['id'] ?? null,
                    'full_name' => $patientName,
                    'national_code' => $nationalCode,
                    'mobile' => $mobile,
                    'file_number' => $fileNumber,
                    'gender' => $patientFromQueue['gender'] ?? null,
                    'birth_date' => $patientFromQueue['birth_date'] ?? null,
                ],

                // فیلدهای تخت (جهت اطمینان)
                'patient_name' => $patientName,
                'national_code' => $nationalCode,
                'file_number' => $fileNumber,
                'mobile' => $mobile,

                // پزشک
                'doctor' => [
                    'id' => $archive->issued_by ?? ($doctorData['id'] ?? null),
                    'name' => $doctorName,
                    'specialty' => $doctorData['specialty'] ?? null,
                ],

                // خدمات و اقدامات
                'services' => $services,
                'form_data' => $formData,
                'attachments' => $attachments,

                // مالی و وضعیت
                'invoice' => $invoice,
                'has_invoice' => $formData['hasInvoice'] ?? false,
                'status' => 'completed',
                'audit_logs' => [],

                'start_at' => $archive->issued_at ?? $archive->created_at,
                'issued_at' => $archive->issued_at ?? $archive->created_at,
                'created_at' => $archive->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $archives,
        ]);
    }

    public function batchDelete(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        return DB::transaction(function () use ($request) {
            $deletedCount = Archive::query()
                ->whereDate('created_at', '>=', $request->from_date)
                ->whereDate('created_at', '<=', $request->to_date)
                ->delete();

            if ($deletedCount === 0) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'هیچ رکوردی در این بازه زمانی یافت نشد.',
                    'deleted_count' => 0,
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => "تعداد {$deletedCount} رکورد با موفقیت حذف شد.",
                'deleted_count' => $deletedCount,
            ]);
        });
    }
}
