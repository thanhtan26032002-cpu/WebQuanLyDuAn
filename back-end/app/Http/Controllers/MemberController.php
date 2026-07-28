<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Member;
use App\Services\GroupMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    // Lấy danh sách toàn bộ thành viên
    public function index()
    {
        $members = Member::select('member_code', 'member_name', 'member_email', 'member_avatar', 'member_color', 'member_role', 'member_phone', 'member_department', 'member_join_date', 'member_bio', 'member_online')->get();

        return response()->json($members);
    }

    // Thêm thành viên mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:members,member_email',
            'role' => 'nullable|string|max:100',
            'phone' => 'required|string|regex:/^\+?[0-9]{9,15}$/',
            'department' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:30',
            'group_code' => 'nullable|exists:groups,group_code',
        ], [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại phải gồm từ 9 đến 15 chữ số và chỉ có thể bắt đầu bằng dấu +.',
        ]);

        $groupCode = $validated['group_code'] ?? null;
        unset($validated['group_code']);

        $member = DB::transaction(function () use ($validated, $groupCode) {
            $member = new Member;
            $member->member_name = $validated['name'];
            $member->member_email = $validated['email'];
            $member->member_role = $validated['role'] ?? 'member';
            $member->member_phone = $validated['phone'] ?? null;
            $member->member_department = $validated['department'] ?? null;
            $member->member_bio = $validated['bio'] ?? null;
            $member->member_color = $validated['color'] ?? 'blue';
            $member->member_join_date = now()->toDateString();
            $member->save();

            GroupMembershipService::assign($member->member_code, $groupCode);

            return $member;
        });

        return response()->json([
            'message' => 'Thêm thành viên thành công',
            'user' => $member,
            'groups' => Group::orderBy('group_created_at')->get(),
        ], 201);
    }

    public function update(Request $request, string $code)
    {
        $member = Member::findOrFail($code);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:members,member_email,'.$code.',member_code',
            'role' => 'nullable|string|max:100',
            'phone' => 'required|string|regex:/^\+?[0-9]{9,15}$/',
            'department' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:30',
            'online' => 'nullable|boolean',
            'group_code' => 'sometimes|nullable|exists:groups,group_code',
        ], [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại phải gồm từ 9 đến 15 chữ số và chỉ có thể bắt đầu bằng dấu +.',
        ]);

        $shouldUpdateGroup = array_key_exists('group_code', $validated);
        $groupCode = $validated['group_code'] ?? null;
        unset($validated['group_code']);

        DB::transaction(function () use ($member, $validated, $shouldUpdateGroup, $groupCode) {
            $member->update(Member::mapToDbAttributes($validated));
            if ($shouldUpdateGroup) {
                GroupMembershipService::assign($member->member_code, $groupCode);
            }
        });

        return response()->json([
            'member' => $member->fresh(),
            'groups' => Group::orderBy('group_created_at')->get(),
        ]);
    }
}
