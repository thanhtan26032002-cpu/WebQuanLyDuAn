<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->user_profile_completed_at) {
            return response()->json([
                'message' => 'Vui lòng hoàn tất hồ sơ trước khi sử dụng không gian làm việc.',
                'requires_profile_completion' => true,
            ], 428);
        }

        return $next($request);
    }
}
