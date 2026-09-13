<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        try {
            // اگر سیستم شما پذیرش‌ها را در appointments ذخیره کرده:
            $query = Appointment::with([
                'patient',
                'doctor',
                'service',
                'invoice',
                'auditLogs.user'
            ]);

            // اگر جدول appointments خالی باشد، می‌توانیم از Patient مستقیماً گزارش بسازیم:
            if (Appointment::count() === 0) {
                $patientQuery = Patient::with(['appointments.invoice', 'appointments.doctor']);

                if ($request->filled('search')) {
                    $search = trim($request->search);
                    $patientQuery->where(function ($q) use ($search) {
                        $q->where('full_name', 'LIKE', "%{$search}%")
                            ->orWhere('national_code', 'LIKE', "%{$search}%")
                            ->orWhere('mobile', 'LIKE', "%{$search}%")
                            ->orWhere('file_number', 'LIKE', "%{$search}%");
                    });
                }

                $patients = $patientQuery->latest()->paginate($request->get('per_page', 10));

                // تبدیل به ساختار مورد انتظار جدول گزارش‌ها
                $patients->getCollection()->transform(function ($p) {
                    return [
                        'id' => $p->id,
                        'patient' => $p,
                        'start_at' => $p->created_at,
                        'created_at' => $p->created_at,
                        'audit_logs' => [],
                        'invoice' => null,
                    ];
                });

                return response()->json([
                    'status' => 'success',
                    'data'   => $patients
                ], 200);
            }

            // فیلتر جستجو روی نوبت‌ها
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'LIKE', "%{$search}%")
                        ->orWhereHas('patient', function ($pq) use ($search) {
                            $pq->where('full_name', 'LIKE', "%{$search}%")
                                ->orWhere('national_code', 'LIKE', "%{$search}%")
                                ->orWhere('mobile', 'LIKE', "%{$search}%")
                                ->orWhere('file_number', 'LIKE', "%{$search}%");
                        });
                });
            }

            // فیلتر تاریخ
            if ($request->filled('from_date')) {
                $query->whereDate('start_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('start_at', '<=', $request->to_date);
            }

            $perPage = (int) $request->get('per_page', 10);
            $reports = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data'   => $reports
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'خطا در دریافت گزارش‌ها: ' . $e->getMessage()
            ], 500);
        }
    }

    public function batchDelete(Request $request)
    {
        $request->validate([
            'from_date' => 'required',
            'to_date'   => 'required',
        ]);

        try {
            DB::beginTransaction();

            $fromDate = $request->from_date;
            $toDate = $request->to_date;

            $appointments = Appointment::whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $toDate)
                ->get();

            $count = $appointments->count();

            if ($count === 0) {
                return response()->json([
                    'status'  => 'warning',
                    'message' => 'هیچ رکوردی در این بازه زمانی یافت نشد.'
                ], 404);
            }

            $ids = $appointments->pluck('id');
            Appointment::whereIn('id', $ids)->delete();

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => "تعداد {$count} رکورد با موفقیت حذف شدند."
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'خطا: ' . $e->getMessage()
            ], 500);
        }
    }
}
