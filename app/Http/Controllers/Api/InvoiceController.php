<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Appointment;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\PayInvoiceRequest;


class InvoiceController extends Controller
{
    /**
     * لیست فاکتورها همراه با صفحه‌بندی و روابط
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['appointment.doctor', 'appointment.service', 'patient']);

        // فیلتر بر اساس وضعیت (در صورت نیاز)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فیلتر بر اساس بیمار
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        $invoices = $query->latest()->paginate(15);

        return response()->json($invoices);
    }

    /**
     * ثبت فاکتور جدید
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $appointment = Appointment::findOrFail($validated['appointment_id']);

        $amount = (int) $validated['amount'];
        $discount = (int) ($validated['discount'] ?? 0);
        $finalAmount = max(0, $amount - $discount);
        $status = $validated['status'] ?? 'unpaid';

        $invoice = DB::transaction(function () use ($appointment, $validated, $amount, $discount, $finalAmount, $status) {
            return Invoice::create([
                'appointment_id' => $appointment->id,
                'patient_id'     => $appointment->patient_id,
                'amount'         => $amount,
                'discount'       => $discount,
                'final_amount'   => $finalAmount,
                'status'         => $status,
                'payment_method' => $validated['payment_method'] ?? null,
                'paid_at'        => $status === 'paid' ? now() : null,
                'notes'          => $validated['notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'فاکتور با موفقیت صادر شد.',
            'data'    => $invoice->load(['appointment', 'patient']),
        ], 201);
    }

    /**
     * نمایش جزئیات یک فاکتور
     */
    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json(
            $invoice->load(['appointment.doctor', 'appointment.service', 'patient'])
        );
    }

    /**
     * ویرایش فاکتور
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validated();

        $amount = array_key_exists('amount', $validated) ? (int) $validated['amount'] : $invoice->amount;
        $discount = array_key_exists('discount', $validated) ? (int) ($validated['discount'] ?? 0) : $invoice->discount;
        $finalAmount = max(0, $amount - $discount);

        $status = $validated['status'] ?? $invoice->status;

        // مدیریت زمان پرداخت بر اساس تغییر وضعیت
        $paidAt = $invoice->paid_at;
        if ($status === 'paid' && !$paidAt) {
            $paidAt = now();
        } elseif ($status !== 'paid') {
            $paidAt = null;
        }

        $invoice->update([
            ...$validated,
            'amount'       => $amount,
            'discount'     => $discount,
            'final_amount' => $finalAmount,
            'status'       => $status,
            'paid_at'      => $paidAt,
        ]);

        return response()->json([
            'message' => 'فاکتور با موفقیت بروزرسانی شد.',
            'data'    => $invoice->fresh()->load(['appointment', 'patient']),
        ]);
    }

    /**
     * حذف فاکتور (می‌توانید به ادمین محدود کنید)
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->json([
            'message' => 'فاکتور با موفقیت حذف شد.',
        ]);
    }

    public function pay(PayInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        // اگر فاکتور قبلاً پرداخت شده باشد
        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'این فاکتور قبلاً پرداخت و تسویه شده است.',
            ], 422);
        }

        // اگر فاکتور لغو شده باشد
        if ($invoice->status === 'cancelled') {
            return response()->json([
                'message' => 'فاکتور لغو شده قابل تسویه نیست.',
            ], 422);
        }

        $validated = $request->validated();

        $invoice->update([
            'status'         => 'paid',
            'payment_method' => $validated['payment_method'],
            'paid_at'        => $validated['paid_at'] ?? now(),
            'notes'          => $validated['notes'] ?? $invoice->notes,
        ]);

        return response()->json([
            'message' => 'فاکتور با موفقیت تسویه و پرداخت شد.',
            'data'    => $invoice->fresh()->load(['appointment.doctor', 'appointment.service', 'patient']),
        ]);
    }
}
