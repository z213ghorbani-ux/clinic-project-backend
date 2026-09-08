<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ورود کاربر با username و password
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        // بررسی صحت رمز و فعال بودن حساب
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['نام کاربری یا رمز عبور اشتباه است.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'حساب کاربری شما غیرفعال است. با مدیر سیستم تماس بگیرید.'
            ], 403);
        }

        // حذف توکن‌های قدیمی (ورود تک‌دستگاهی)
        $user->tokens()->delete();

        // صدور توکن جدید
        $token = $user->createToken('clinic-panel')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'خوش آمدید ' . $user->name,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role,
                ],
            ]
        ]);
    }

    /**
     * خروج کاربر و حذف توکن
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'با موفقیت خارج شدید.'
        ]);
    }

    /**
     * اطلاعات کاربر لاگین‌شده
     */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()
        ]);
    }
}
