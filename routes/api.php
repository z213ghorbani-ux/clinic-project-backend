<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Api\ReportController;






// ---------- احراز هویت ----------
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// ---------- مسیرهای محافظت‌شده ----------
Route::middleware('auth:sanctum')->group(function () {

    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay']);


    // مشاهده لیست و جزئیات: همه سطوح
    Route::get('doctors', [DoctorController::class, 'index']);
    Route::get('doctors/{doctor}', [DoctorController::class, 'show']);
    Route::get('patients', [PatientController::class, 'index']);
    Route::get('patients/{patient}', [PatientController::class, 'show']);

    // ثبت و ویرایش: همه سطوح (Admin + Staff 2 + Staff 1)
    Route::post('doctors', [DoctorController::class, 'store']);
    Route::put('doctors/{doctor}', [DoctorController::class, 'update']);
    Route::patch('doctors/{doctor}', [DoctorController::class, 'update']);
    Route::post('patients', [PatientController::class, 'store']);
    Route::put('patients/{patient}', [PatientController::class, 'update']);
    Route::patch('patients/{patient}', [PatientController::class, 'update']);

    // حذف: فقط ادمین
    Route::delete('doctors/{doctor}', [DoctorController::class, 'destroy'])
        ->middleware('role:admin');
    Route::delete('patients/{patient}', [PatientController::class, 'destroy'])
        ->middleware('role:admin');

    Route::apiResource('services', ServiceController::class);

    Route::apiResource('appointments', AppointmentController::class);


    Route::apiResource('invoices', InvoiceController::class);




    Route::get('/archives', [ArchiveController::class, 'index']);
    Route::get('/archives/{archive}', [ArchiveController::class, 'show']);
    Route::delete('/archives/{archive}', [ArchiveController::class, 'destroy']);
    Route::post('/archives/bulk-delete', [ArchiveController::class, 'bulkDelete']);
    Route::get('/archives/{archive}/attachments/{index}', [ArchiveController::class, 'downloadAttachment']);

    Route::get('/archives', [ArchiveController::class, 'index']);
    Route::post('/archives', [ArchiveController::class, 'store']); // <-- اضافه شد
    Route::get('/archives/{archive}', [ArchiveController::class, 'show']);
    Route::delete('/archives/{archive}', [ArchiveController::class, 'destroy']);
    Route::post('/archives/bulk-delete', [ArchiveController::class, 'bulkDelete']);
    Route::get('/archives/{archive}/attachments/{index}', [ArchiveController::class, 'downloadAttachment']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports/batch-delete', [ReportController::class, 'batchDelete']);
    
});
