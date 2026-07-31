<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::select(
            'user_code', 'user_name', 'user_email', 'user_avatar', 'user_role',
            'user_color', 'user_job_title', 'user_department', 'user_profile_completed_at'
        );
        if (! AccessService::canViewPrivateProfiles($request->user())) {
            $query->whereKey($request->user()->user_code);
        }

        return response()->json($query->get());
    }

    public function updateProfile(Request $request, string $code)
    {
        $user = User::findOrFail($code);
        AccessService::authorize(
            $request->user()->user_code === $user->user_code || AccessService::isAdmin($request->user())
        );

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,user_email,'.$user->user_code.',user_code',
            'role' => 'sometimes|required|in:admin,project_manager,member',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:30',
            'weekly_capacity_hours' => 'nullable|numeric|min:1|max:168',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'name', 'email', 'role', 'phone', 'department', 'job_title',
            'bio', 'color', 'weekly_capacity_hours',
        ]);
        if (array_key_exists('role', $data) && ! AccessService::isAdmin($request->user())) {
            unset($data['role']);
        }
        if (
            isset($data['role'])
            && $request->user()->user_code === $user->user_code
            && $data['role'] !== $user->user_role
        ) {
            return response()->json([
                'errors' => ['role' => ['Quản trị viên không thể tự thay đổi vai trò của chính mình.']],
            ], 422);
        }

        if ($request->hasFile('avatar')) {
            if ($user->user_avatar && Storage::disk('public')->exists(str_replace('/storage/', '', $user->user_avatar))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->user_avatar));
            }
            $data['avatar'] = '/storage/'.$request->file('avatar')->store('avatars', 'public');
        }

        $profileData = array_merge($user->toArray(), $data);
        if ($this->profileComplete($profileData)) {
            $data['profile_completed_at'] = $user->user_profile_completed_at ?: now();
        }

        $user->update(User::mapToDbAttributes($data));

        return response()->json(['message' => 'Đã cập nhật hồ sơ.', 'user' => $user->fresh()]);
    }

    public function completeProfile(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|regex:/^\+?[0-9]{9,15}$/',
            'department' => 'required|string|max:100',
            'job_title' => 'required|string|max:100',
            'bio' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:30',
        ]);

        $request->user()->update(User::mapToDbAttributes([
            ...$validated,
            'profile_completed_at' => now(),
            'join_date' => $request->user()->user_join_date ?: now()->toDateString(),
            'online' => true,
        ]));

        return response()->json([
            'message' => 'Đã hoàn tất hồ sơ thành viên.',
            'user' => $request->user()->fresh(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ], [
            'password.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->user_password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không chính xác.'],
            ]);
        }

        $request->user()->update([
            'user_password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'Đã đổi mật khẩu thành công.']);
    }

    private function profileComplete(array $data): bool
    {
        return filled($data['name'] ?? null)
            && filled($data['phone'] ?? null)
            && filled($data['department'] ?? null)
            && filled($data['job_title'] ?? null);
    }
}
