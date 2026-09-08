<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * لیست خدمات همراه با صفحه‌بندی و قابلیت فیلتر وضعیت
     */
    public function index(Request $request): JsonResponse
    {
        $query = Service::query();

        // امکان فیلتر بر اساس فعال/غیرفعال بودن در صورت نیاز
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // جستجو بر اساس نام خدمت
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $services = $query->latest()->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $services,
        ]);
    }

    /**
     * ایجاد خدمت جدید
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'خدمت با موفقیت تعریف شد.',
            'data' => $service,
        ], 201);
    }

    /**
     * مشاهده تکی یک خدمت
     */
    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $service,
        ]);
    }

    /**
     * ویرایش خدمت
     */
    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'خدمت با موفقیت ویرایش شد.',
            'data' => $service,
        ]);
    }

    /**
     * حذف خدمت
     */
    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'خدمت با موفقیت حذف شد.',
        ]);
    }
}
