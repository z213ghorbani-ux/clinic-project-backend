<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ArchiveController extends Controller
{
    // ثبت پرونده جوابدهی جدید (چند خدمت + فایل‌های پیوست)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_name'   => 'required|string|max:255',
            'national_code'  => 'required|string|max:20',
            'file_number'    => 'nullable|string|max:50',
            'mobile'         => 'nullable|string|max:20',
            'issued_at'      => 'required|date',
            'form_data'      => 'nullable', // می‌تواند رشته JSON یا آرایه باشد
            'files'          => 'nullable|array',
            'files.*'        => 'file|max:20480', // حداکثر 20 مگابایت برای هر فایل
        ]);

        // ذخیره فایل‌های پیوست در استوریج عمومی
        $attachmentPaths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('archives/' . $validated['national_code'], 'public');
                $attachmentPaths[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ];
            }
        }

        // پردازش فرم داده (خدمات انجام‌شده، مبالغ، توضیحات)
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
            'message' => 'پرونده جوابدهی با موفقیت در سیستم بایگانی و ذخیره شد.',
            'data'    => $archive,
        ], 201);
    }

    // لیست با فیلتر از تاریخ / تا تاریخ و جستجو
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

    // مشاهده جزئیات یک رکورد
    public function show(Archive $archive)
    {
        return response()->json($archive);
    }

    // حذف یک رکورد
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

    // حذف گروهی بر اساس بازه تاریخ
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

    // دانلود فایل پیوست
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
