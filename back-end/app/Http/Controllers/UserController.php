<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // Lấy danh sách toàn bộ thành viên
    public function index()
    {
        $users = User::select('user_code', 'user_name', 'user_email', 'user_avatar', 'user_role')->get();
        return response()->json($users);
    }

    public function updateProfile(Request $request, $code)
    {
        $user = User::where('user_code', $code)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,user_email,' . $user->user_code . ',user_code',
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
            if ($user->user_avatar && Storage::disk('public')->exists(str_replace('/storage/', '', $user->user_avatar))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->user_avatar));
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = '/storage/' . $path;
        }

        $user->update(User::mapToDbAttributes($data));

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }
}
