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

        // فیلتر جستجو
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'like', "%{$search}%")
                    ->orWhere('national_code', 'like', "%{$search}%")
                    ->orWhere('file_number', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('issued_by_name', 'like', "%{$search}%")
                    ->orWhere('form_data', 'like', "%{$search}%");
            });
        }

        // فیلتر تاریخ‌ها
        if ($request->filled('from_date')) {
            $query->whereDate('issued_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('issued_at', '<=', $request->to_date);
        }

        $archives = $query->latest('id')->paginate($perPage);

        $archives->getCollection()->transform(function ($archive) {
            $formData = is_array($archive->form_data)
                ? $archive->form_data
                : (json_decode($archive->form_data, true) ?? []);

            $attachments = is_array($archive->attachments)
                ? $archive->attachments
                : (json_decode($archive->attachments, true) ?? []);

            // ۱. استخراج اطلاعات بیمار
            $queueItem = $formData['invoiceDetails']['updatedQueue'][0] ?? [];
            $patientFromQueue = $queueItem['patient'] ?? [];

            $patientName = $archive->patient_name
                ?? ($patientFromQueue['full_name'] ?? null)
                ?? ($patientFromQueue['name'] ?? null)
                ?? ($formData['patient_name'] ?? null)
                ?? 'نامشخص';

            $nationalCode = $archive->national_code
                ?? ($patientFromQueue['national_code'] ?? null)
                ?? ($formData['national_code'] ?? null)
                ?? '-';

            $mobile = $archive->mobile
                ?? ($patientFromQueue['mobile'] ?? null)
                ?? ($patientFromQueue['phone'] ?? null)
                ?? ($formData['mobile'] ?? null)
                ?? '-';

            $fileNumber = $archive->file_number
                ?? ($patientFromQueue['file_number'] ?? null)
                ?? ($patientFromQueue['case_number'] ?? null)
                ?? (string) $archive->id;

            // ۲. خدمات و توضیحات اقدامات
            $services = [];
            $actionNotes = [];

            if (isset($formData['invoiceDetails']['updatedQueue'])) {
                foreach ($formData['invoiceDetails']['updatedQueue'] as $q) {
                    if (!empty($q['services']) && is_array($q['services'])) {
                        foreach ($q['services'] as $s) {
                            $sName = is_array($s) ? ($s['title'] ?? $s['name'] ?? 'خدمت ثبت‌شده') : (string) $s;
                            $services[] = $sName;
                            $actionNotes[] = "ثبت خدمت: " . $sName;
                        }
                    }
                }
            }

            if (!empty($formData['doctor_note'])) {
                $actionNotes[] = "یادداشت پزشک: " . $formData['doctor_note'];
            }
            if (!empty($formData['description'])) {
                $actionNotes[] = $formData['description'];
            }

            $actionDescription = !empty($actionNotes)
                ? implode(' | ', $actionNotes)
                : 'پرونده و جوابدهی در سامانه بایگانی گردید.';

            // ساخت ساختار لاگ برای بخش چرخه جوابدهی
            $auditLogs = [
                [
                    'id' => 1,
                    'user' => [
                        'name' => $archive->issued_by_name ?? 'کاربر سیستم',
                    ],
                    'created_at' => substr($archive->issued_at ?? $archive->created_at, 0, 16),
                    'action_description' => $actionDescription,
                    'action' => 'بایگانی و ثبت نهایی',
                ]
            ];

            // ۳. فاکتور مالی با کلیدهای مد نظر React
            $invoiceDetails = $formData['invoiceDetails'] ?? null;
            $invoice = null;

            if ($invoiceDetails || ($formData['hasInvoice'] ?? false)) {
                $payable = $invoiceDetails['payableAmount']
                    ?? $invoiceDetails['totalPrice']
                    ?? $formData['payable_amount']
                    ?? $formData['total_price']
                    ?? 0;

                $total = $invoiceDetails['totalPrice']
                    ?? $payable;

                $invoice = [
                    'id' => $archive->id,
                    'final_amount' => (float) $payable,
                    'total_amount' => (float) $total,
                    'discount' => (float) ($invoiceDetails['discount'] ?? 0),
                    'is_paid' => true,
                    'payment_method' => 'نقدی / کارت‌خوان',
                    'services' => $services,
                ];
            }

            return [
                'id' => $archive->id,

                // هماهنگ با: row.patient?.name و row.patient?.phone و row.patient?.case_number
                'patient' => [
                    'id' => $patientFromQueue['id'] ?? $archive->id,
                    'name' => $patientName,
                    'full_name' => $patientName,
                    'national_code' => $nationalCode,
                    'phone' => $mobile,
                    'mobile' => $mobile,
                    'case_number' => $fileNumber,
                    'file_number' => $fileNumber,
                ],

                'patient_name' => $patientName,
                'national_code' => $nationalCode,
                'mobile' => $mobile,
                'file_number' => $fileNumber,

                // چرخه جوابدهی و اقدامات کاربران
                'audit_logs' => $auditLogs,

                // فاکتور
                'invoice' => $invoice,

                'form_data' => $formData,
                'attachments' => $attachments,
                'status' => 'completed',
                'created_at' => (string) ($archive->issued_at ?? $archive->created_at),
                'issued_at' => (string) ($archive->issued_at ?? $archive->created_at),
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
                ->whereDate('issued_at', '>=', $request->from_date)
                ->whereDate('issued_at', '<=', $request->to_date)
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
