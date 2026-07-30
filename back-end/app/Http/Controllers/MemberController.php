<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Services\AccessService;
use App\Services\GroupMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $canSeePrivateProfiles = AccessService::canManagePeople($request->user());
        $currentUserCode = $request->user()->user_code;

        $users = User::orderBy('user_name')->get()->map(function (User $user) use ($canSeePrivateProfiles, $currentUserCode) {
            if ($canSeePrivateProfiles || $user->user_code === $currentUserCode) {
                return $user;
            }

            return [
                'code' => $user->user_code,
                'name' => $user->user_name,
                'avatar' => $user->user_avatar,
                'color' => $user->user_color,
                'job_title' => $user->user_job_title,
                'department' => $user->user_department,
                'online' => $user->user_online,
                'profile_limited' => true,
            ];
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        AccessService::authorize(AccessService::canManagePeople($request->user()));
        $validated = $request->validate($this->rules(null, true), $this->messages());

        $groupCode = $validated['group_code'] ?? null;
        $systemRole = AccessService::isAdmin($request->user())
            ? ($validated['system_role'] ?? 'member')
            : 'member';

        $user = DB::transaction(function () use ($validated, $groupCode, $systemRole) {
            $user = User::create([
                'user_name' => trim($validated['name']),
                'user_email' => mb_strtolower($validated['email']),
                'user_password' => Hash::make($validated['password']),
                'user_role' => $systemRole,
                'user_job_title' => $validated['job_title'] ?? 'Nhân viên',
                'user_phone' => $validated['phone'],
                'user_department' => $validated['department'] ?? null,
                'user_bio' => $validated['bio'] ?? null,
                'user_color' => $validated['color'] ?? 'blue',
                'user_join_date' => now()->toDateString(),
                'user_online' => true,
                'user_weekly_capacity_hours' => $validated['weekly_capacity_hours'] ?? 40,
                'user_profile_completed_at' => now(),
            ]);

            GroupMembershipService::assign($user->user_code, $groupCode);

            return $user;
        });

        return response()->json([
            'message' => 'Đã tạo tài khoản thành viên.',
            'user' => $user,
            'groups' => Group::orderBy('group_created_at')->get(),
        ], 201);
    }

    public function update(Request $request, string $code)
    {
        $user = User::findOrFail($code);
        $canManage = AccessService::canManagePeople($request->user());
        AccessService::authorize($canManage || $request->user()->user_code === $code);

        $validated = $request->validate($this->rules($code, false), $this->messages());
        if (! AccessService::isAdmin($request->user())) {
            unset($validated['system_role']);
        }
        if (
            isset($validated['system_role'])
            && $request->user()->user_code === $user->user_code
            && $validated['system_role'] !== $user->user_role
        ) {
            return response()->json([
                'errors' => ['system_role' => ['Quản trị viên không thể tự thay đổi vai trò của chính mình.']],
            ], 422);
        }

        $shouldUpdateGroup = $canManage && array_key_exists('group_code', $validated);
        $groupCode = $validated['group_code'] ?? null;
        unset($validated['group_code']);

        $data = [
            'name' => $validated['name'] ?? $user->user_name,
            'email' => isset($validated['email']) ? mb_strtolower($validated['email']) : $user->user_email,
            'job_title' => $validated['job_title'] ?? $user->user_job_title,
            'phone' => $validated['phone'] ?? $user->user_phone,
            'department' => $validated['department'] ?? $user->user_department,
            'bio' => $validated['bio'] ?? $user->user_bio,
            'color' => $validated['color'] ?? $user->user_color,
            'online' => $validated['online'] ?? $user->user_online,
            'weekly_capacity_hours' => $validated['weekly_capacity_hours'] ?? $user->user_weekly_capacity_hours,
        ];
        if (isset($validated['system_role'])) {
            $data['role'] = $validated['system_role'];
        }
        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }
        if ($this->profileComplete($data)) {
            $data['profile_completed_at'] = $user->user_profile_completed_at ?: now();
        }

        DB::transaction(function () use ($user, $data, $shouldUpdateGroup, $groupCode) {
            $user->update(User::mapToDbAttributes($data));
            if ($shouldUpdateGroup) {
                GroupMembershipService::assign($user->user_code, $groupCode);
            }
        });

        return response()->json([
            'member' => $user->fresh(),
            'groups' => Group::orderBy('group_created_at')->get(),
        ]);
    }

    private function rules(?string $code, bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255', Rule::unique('users', 'user_email')->ignore($code, 'user_code')],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
            'system_role' => 'nullable|in:admin,project_manager,member',
            'job_title' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'phone' => [$creating ? 'required' : 'sometimes', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'department' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'bio' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:30',
            'online' => 'nullable|boolean',
            'group_code' => 'sometimes|nullable|exists:groups,group_code',
            'weekly_capacity_hours' => 'nullable|numeric|min:1|max:168',
        ];
    }

    private function messages(): array
    {
        return [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.string' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại phải gồm từ 9 đến 15 chữ số và chỉ có thể bắt đầu bằng dấu +.',
        ];
    }

    private function profileComplete(array $data): bool
    {
        return filled($data['name'] ?? null)
            && filled($data['phone'] ?? null)
            && filled($data['department'] ?? null)
            && filled($data['job_title'] ?? null);
    }
}
