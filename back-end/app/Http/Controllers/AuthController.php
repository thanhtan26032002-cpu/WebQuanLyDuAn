<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,user_email',
            'password' => 'required|string|min:6',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $member = Member::firstOrCreate(
                ['member_email' => mb_strtolower($validated['email'])],
                [
                    'member_name' => $validated['name'],
                    'member_role' => 'member',
                    'member_join_date' => now()->toDateString(),
                ]
            );

            return User::create([
                'user_name' => $validated['name'],
                'user_email' => mb_strtolower($validated['email']),
                'user_password' => Hash::make($validated['password']),
                'user_role' => User::count() === 0 ? 'admin' : 'member',
                'user_member_code' => $member->member_code,
                'user_notification_preferences' => $this->defaultNotificationPreferences(),
            ]);
        });

        $token = $this->issueToken($user);

        return response()->json([
            'message' => 'Đăng ký thành công',
            'user' => $user->load('member'),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('user_email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->user_password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.'],
            ]);
        }

        $token = $this->issueToken($user);

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user->load('member'),
            'token' => $token,
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->forceFill(['user_api_token' => null])->save();

        return response()->json([
            'message' => 'Đăng xuất thành công',
        ]);
    }

    private function issueToken(User $user): string
    {
        $plainTextToken = Str::random(80);
        $user->forceFill([
            'user_api_token' => hash('sha256', $plainTextToken),
        ])->save();

        return $plainTextToken;
    }

    private function defaultNotificationPreferences(): array
    {
        return [
            'assignment' => true,
            'deadline' => true,
            'comments' => true,
            'mentions' => true,
            'blocked' => true,
        ];
    }
}
