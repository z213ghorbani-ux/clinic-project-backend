<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    /**
     * نمایش فهرست نوبت‌ها
     */
    public function index(Request $request): JsonResponse
    {
        $appointments = Appointment::with([
            'doctor',
            'patient',
            'service',
        ])
            ->when(
                $request->filled('status'),
                fn($query) => $query->where('status', $request->status)
            )
            ->when(
                $request->filled('doctor_id'),
                fn($query) => $query->where('doctor_id', $request->doctor_id)
            )
            ->when(
                $request->filled('patient_id'),
                fn($query) => $query->where('patient_id', $request->patient_id)
            )
            ->orderBy('start_at')
            ->paginate(15);

        return response()->json($appointments);
    }

    /**
     * ایجاد نوبت جدید
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => [
                'required',
                'integer',
                'exists:doctors,id',
            ],
            'patient_id' => [
                'required',
                'integer',
                'exists:patients,id',
            ],
            'service_id' => [
                'required',
                'integer',
                'exists:services,id',
            ],
            'start_at' => [
                'required',
                'date',
            ],
            'end_at' => [
                'required',
                'date',
                'after:start_at',
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'scheduled',
                    'confirmed',
                    'completed',
                    'cancelled',
                    'no_show',
                ]),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $this->ensureNoConflict(
            $validated['doctor_id'],
            $validated['patient_id'],
            $validated['start_at'],
            $validated['end_at']
        );

        $appointment = Appointment::create($validated);

        return response()->json([
            'message' => 'Appointment created successfully.',
            'data' => $appointment->load([
                'doctor',
                'patient',
                'service',
            ]),
        ], 201);
    }

    /**
     * نمایش یک نوبت
     */
    public function show(Appointment $appointment): JsonResponse
    {
        return response()->json([
            'data' => $appointment->load([
                'doctor',
                'patient',
                'service',
            ]),
        ]);
    }

    /**
     * ویرایش نوبت
     */
    public function update(
        Request $request,
        Appointment $appointment
    ): JsonResponse {
        $validated = $request->validate([
            'doctor_id' => [
                'sometimes',
                'integer',
                'exists:doctors,id',
            ],
            'patient_id' => [
                'sometimes',
                'integer',
                'exists:patients,id',
            ],
            'service_id' => [
                'sometimes',
                'integer',
                'exists:services,id',
            ],
            'start_at' => [
                'sometimes',
                'date',
            ],
            'end_at' => [
                'sometimes',
                'date',
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'scheduled',
                    'confirmed',
                    'completed',
                    'cancelled',
                    'no_show',
                ]),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $doctorId = $validated['doctor_id'] ?? $appointment->doctor_id;
        $patientId = $validated['patient_id'] ?? $appointment->patient_id;
        $startAt = $validated['start_at'] ?? $appointment->start_at;
        $endAt = $validated['end_at'] ?? $appointment->end_at;

        if (isset($validated['start_at']) && !isset($validated['end_at'])) {
            if ($appointment->end_at->lte($startAt)) {
                return response()->json([
                    'message' => 'end_at must be after start_at.',
                ], 422);
            }
        }

        if (isset($validated['end_at']) && !isset($validated['start_at'])) {
            if ($endAt <= $appointment->start_at) {
                return response()->json([
                    'message' => 'end_at must be after start_at.',
                ], 422);
            }
        }

        $this->ensureNoConflict(
            $doctorId,
            $patientId,
            $startAt,
            $endAt,
            $appointment->id
        );

        $appointment->update($validated);

        return response()->json([
            'message' => 'Appointment updated successfully.',
            'data' => $appointment->fresh()->load([
                'doctor',
                'patient',
                'service',
            ]),
        ]);
    }

    /**
     * حذف نوبت
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json([
            'message' => 'Appointment deleted successfully.',
        ]);
    }

    /**
     * جلوگیری از تداخل زمانی پزشک یا بیمار
     */
    private function ensureNoConflict(
        int $doctorId,
        int $patientId,
        $startAt,
        $endAt,
        ?int $ignoreAppointmentId = null
    ): void {
        $hasConflict = Appointment::query()
            ->where(function ($query) use ($doctorId, $patientId) {
                $query->where('doctor_id', $doctorId)
                    ->orWhere('patient_id', $patientId);
            })
            ->whereIn('status', [
                'scheduled',
                'confirmed',
            ])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->when(
                $ignoreAppointmentId,
                fn($query) => $query->where(
                    'id',
                    '!=',
                    $ignoreAppointmentId
                )
            )
            ->exists();

        if ($hasConflict) {
            abort(422, 'The doctor or patient already has an overlapping appointment.');
        }
    }
}
