<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Lấy danh sách toàn bộ thành viên
    public function index(Request $request)
    {
        $query = User::select('user_code', 'user_name', 'user_email', 'user_avatar', 'user_role', 'user_member_code');
        if (! AccessService::canManagePeople($request->user())) {
            $query->whereKey($request->user()->user_code);
        }
        $users = $query->get();

        return response()->json($users);
    }

    public function updateProfile(Request $request, $code)
    {
        $user = User::where('user_code', $code)->first();
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        AccessService::authorize(
            $request->user()->user_code === $user->user_code || AccessService::isAdmin($request->user())
        );

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,user_email,'.$user->user_code.',user_code',
            'role' => 'sometimes|required|in:admin,project_manager,member,viewer',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['name', 'email', 'role', 'phone', 'department']);
        if (array_key_exists('role', $data) && ! AccessService::isAdmin($request->user())) {
            unset($data['role']);
        }

        if ($request->hasFile('avatar')) {
            // Xóa avatar cũ nếu có
            if ($user->user_avatar && Storage::disk('public')->exists(str_replace('/storage/', '', $user->user_avatar))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->user_avatar));
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = '/storage/'.$path;
        }

        $user->update(User::mapToDbAttributes($data));

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }
}
