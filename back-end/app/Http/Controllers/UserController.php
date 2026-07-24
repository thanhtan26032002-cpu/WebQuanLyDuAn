<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Lấy danh sách toàn bộ thành viên
    public function index()
    {
        $users = User::select('code', 'name', 'email', 'avatar', 'role')->get(); // Fixed id to code
        return response()->json($users);
    }

    public function updateProfile(Request $request, $code)
    {
        $user = User::where('code', $code)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->code . ',code',
            'role' => 'sometimes|required|string',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['name', 'email', 'role', 'phone', 'department']);

        if ($request->hasFile('avatar')) {
            // Xóa avatar cũ nếu có
            if ($user->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar))) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = '/storage/' . $path;
        }

        $user->update($data);

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }
}
