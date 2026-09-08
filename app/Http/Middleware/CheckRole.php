<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    /**
     * بررسی نقش کاربر با لیست نقش‌های مجاز
     * استفاده: ->middleware('role:admin,staff_level_2')
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'ابتدا وارد سیستم شوید.'
            ], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'شما دسترسی لازم برای این عملیات را ندارید.'
            ], 403);
        }

        return $next($request);
    }
}
