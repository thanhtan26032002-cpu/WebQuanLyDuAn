<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
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

        $user = User::create([
            'user_name' => $validated['name'],
            'user_email' => $validated['email'],
            'user_password' => Hash::make($validated['password']),
        ]);

        // Trả về response JSON
        return response()->json([
            'message' => 'Đăng ký thành công',
            'user' => $user,
            'token' => method_exists($user, 'createToken') ? $user->createToken('auth_token')->plainTextToken : 'mock_token_' . $user->user_code,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('user_email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->user_password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.'],
            ]);
        }

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user,
            'token' => method_exists($user, 'createToken') ? $user->createToken('auth_token')->plainTextToken : 'mock_token_' . $user->user_code,
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        if ($request->user() && method_exists($request->user(), 'currentAccessToken') && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ]);
    }
}
