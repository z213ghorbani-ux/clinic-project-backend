<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\Doctor;
use App\Services\SignatureStampService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ArchiveController extends Controller
{
    protected SignatureStampService $stampService;

    public function __construct(SignatureStampService $stampService)
    {
        $this->stampService = $stampService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_name'   => 'required|string|max:255',
            'national_code'  => 'required|string|max:20',
            'file_number'    => 'nullable|string|max:50',
            'mobile'         => 'nullable|string|max:20',
            'issued_at'      => 'required|date',
            'form_data'      => 'nullable',
            'files'          => 'nullable|array',
            'files.*'        => 'file|max:20480',
            'file_doctors'   => 'nullable|array', // شناسه پزشک برای هر فایل به ترتیب
            'file_doctors.*' => 'nullable|integer',
        ]);

        $fileDoctors = $request->input('file_doctors', []);
        $attachmentPaths = [];

        if ($request->hasFile('files')) {
            $files = $request->file('files');
            foreach ($files as $index => $file) {
                // دریافت دکتری که برای این فایل انتخاب شده
                $doctorId = $fileDoctors[$index] ?? null;
                $stampPath = null;

                if ($doctorId) {
                    $doctor = Doctor::find($doctorId);
                    $stampPath = $doctor?->stamp_path;
                }

                // اعمال مهر و ذخیره در استوریج
                $saveDirectory = 'archives/' . $validated['national_code'];
                $path = $this->stampService->applyStampAndSave($file, $stampPath, $saveDirectory);

                $attachmentPaths[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : $file->getSize(),
                    'doctor_id'     => $doctorId,
                ];
            }
        }

        $formData = $request->input('form_data');
        if (is_string($formData)) {
            $formData = json_decode($formData, true);
        }

        $user = Auth::user();

        $archive = Archive::create([
            'patient_name'   => $validated['patient_name'],
            'national_code'  => $validated['national_code'],
            'file_number'    => $validated['file_number'] ?? null,
            'mobile'         => $validated['mobile'] ?? null,
            'issued_by'      => $user ? $user->id : null,
            'issued_by_name' => $user ? ($user->name ?? $user->username ?? 'کاربر سیستم') : 'ثبت مستقیم',
            'issued_at'      => $validated['issued_at'],
            'form_data'      => $formData,
            'attachments'    => $attachmentPaths,
        ]);

        return response()->json([
            'message' => 'پرونده جوابدهی با موفقیت در سیستم بایگانی و ممهور شد.',
            'data'    => $archive,
        ], 201);
    }

    public function index(Request $request)
    {
        $query = Archive::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('patient_name', 'like', "%{$s}%")
                    ->orWhere('national_code', 'like', "%{$s}%")
                    ->orWhere('file_number', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('issued_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('issued_at', '<=', $request->to_date);
        }

        return response()->json(
            $query->orderByDesc('issued_at')
                ->paginate($request->get('per_page', 10))
        );
    }

    public function show(Archive $archive)
    {
        return response()->json($archive);
    }

    public function destroy(Archive $archive)
    {
        if ($archive->attachments) {
            $attachments = is_array($archive->attachments)
                ? $archive->attachments
                : json_decode($archive->attachments, true);

            if (is_array($attachments)) {
                foreach ($attachments as $item) {
                    $path = is_array($item) ? ($item['path'] ?? null) : $item;
                    if ($path && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
        }

        $archive->delete();

        return response()->json([
            'message' => 'رکورد بایگانی با موفقیت حذف شد'
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        $archives = Archive::whereDate('issued_at', '>=', $request->from_date)
            ->whereDate('issued_at', '<=', $request->to_date)->get();

        foreach ($archives as $archive) {
            if ($archive->attachments) {
                $attachments = is_array($archive->attachments)
                    ? $archive->attachments
                    : json_decode($archive->attachments, true);

                if (is_array($attachments)) {
                    foreach ($attachments as $item) {
                        $path = is_array($item) ? ($item['path'] ?? null) : $item;
                        if ($path && Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                }
            }
            $archive->delete();
        }

        return response()->json([
            'message' => "{$archives->count()} رکورد بایگانی حذف شد",
            'deleted_count' => $archives->count(),
        ]);
    }

    public function downloadAttachment(Archive $archive, $index)
    {
        $paths = is_array($archive->attachments)
            ? $archive->attachments
            : json_decode($archive->attachments ?? '[]', true);

        $target = $paths[$index] ?? null;
        $path = is_array($target) ? ($target['path'] ?? null) : $target;

        if (!$path || !Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'فایل یافت نشد'], 404);
        }

        return Storage::disk('public')->download($path);
    }
}
