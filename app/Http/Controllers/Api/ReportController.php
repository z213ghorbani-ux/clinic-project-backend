<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Appointment::with([
            'patient',
            'doctor',
            'service',
            'invoice',
            'auditLogs.user',
        ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($patientQuery) use ($search) {
                        $patientQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->orWhere('national_code', 'like', "%{$search}%")
                            ->orWhere('file_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('start_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('start_at', '<=', $request->to_date);
        }

        $reports = $query->latest('id')->paginate($perPage);

        $reports->getCollection()->transform(function ($appointment) {
            return [
                'id' => $appointment->id,

                'patient' => $appointment->patient ? [
                    'id' => $appointment->patient->id,
                    'full_name' => $appointment->patient->full_name,
                    'mobile' => $appointment->patient->mobile,
                    'national_code' => $appointment->patient->national_code,
                    'file_number' => $appointment->patient->file_number,
                ] : null,

                'doctor' => $appointment->doctor ? [
                    'id' => $appointment->doctor->id,
                    'name' => $appointment->doctor->name ?? $appointment->doctor->full_name,
                ] : null,

                'service' => $appointment->service ? [
                    'id' => $appointment->service->id,
                    'name' => $appointment->service->name,
                ] : null,

                'start_at' => $appointment->start_at,
                'created_at' => $appointment->created_at,
                'status' => $appointment->status,

                'invoice' => $appointment->invoice ? [
                    'id' => $appointment->invoice->id,
                    'total_amount' => $appointment->invoice->total_amount,
                    'discount' => $appointment->invoice->discount,
                    'final_amount' => $appointment->invoice->final_amount,
                    'status' => $appointment->invoice->status,
                ] : null,

                'audit_logs' => $appointment->auditLogs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'description' => $log->description,
                        'created_at' => $log->created_at,
                        'user' => $log->user ? [
                            'id' => $log->user->id,
                            'name' => $log->user->name,
                        ] : null,
                    ];
                })->values(),

            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $reports,
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
            $query = Appointment::whereDate('created_at', '>=', $validated['from_date'])
                ->whereDate('created_at', '<=', $validated['to_date']);

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
