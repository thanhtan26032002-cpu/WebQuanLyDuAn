<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    // Lấy danh sách toàn bộ thành viên
    public function index()
    {
        $members = Member::select('member_code', 'member_name', 'member_email', 'member_avatar', 'member_role', 'member_phone', 'member_department', 'member_join_date', 'member_bio', 'member_online')->get();

        return response()->json($members);
    }

    // Thêm thành viên mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:members,member_email',
            'role' => 'nullable|string|max:100',
            'phone' => 'nullable|string|regex:/^[0-9\+\-\(\)\s]{7,20}$/',
            'department' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
        ]);

        $member = new Member;
        $member->member_name = $validated['name'];
        $member->member_email = $validated['email'];
        $member->member_role = $validated['role'] ?? 'member';
        $member->member_phone = $validated['phone'] ?? null;
        $member->member_department = $validated['department'] ?? null;
        $member->member_bio = $validated['bio'] ?? null;
        $member->member_join_date = now()->toDateString();
        $member->save();

        return response()->json([
            'message' => 'Thêm thành viên thành công',
            'user' => $member,
        ], 201);
    }

    public function update(Request $request, string $code)
    {
        $member = Member::findOrFail($code);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:members,member_email,'.$code.',member_code',
            'role' => 'nullable|string|max:100',
            'phone' => 'nullable|string|regex:/^[0-9\+\-\(\)\s]{7,20}$/',
            'department' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'online' => 'nullable|boolean',
        ]);

        $member->update(Member::mapToDbAttributes($validated));

        return response()->json(['member' => $member->fresh()]);
    }
}
