<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    // Lấy danh sách toàn bộ thành viên
    public function index()
    {
        $members = Member::select('code', 'name', 'email', 'avatar', 'role', 'phone', 'department', 'join_date', 'bio', 'online')->get();
        return response()->json($members);
    }

    // Thêm thành viên mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:members',
            'role' => 'nullable|string|max:100',
            'phone' => 'nullable|string|regex:/^[0-9\+\-\(\)\s]{7,20}$/',
            'department' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
        ]);

        $member = new Member();
        $member->name = $validated['name'];
        $member->email = $validated['email'];
        $member->role = $validated['role'] ?? 'member';
        $member->phone = $validated['phone'] ?? null;
        $member->department = $validated['department'] ?? null;
        $member->bio = $validated['bio'] ?? null;
        $member->join_date = now()->toDateString();
        $member->save();

        return response()->json([
            'message' => 'Thêm thành viên thành công',
            'user' => $member
        ], 201);
    }
}
