<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Member;
use App\Services\GroupMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index()
    {
        return response()->json(Group::orderBy('group_created_at')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:30',
        ]);

        $validated['member_ids'] = [];
        $group = Group::create(Group::mapToDbAttributes($validated));

        return response()->json(['group' => $group], 201);
    }

    public function update(Request $request, string $code)
    {
        $group = Group::findOrFail($code);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:30',
        ]);

        $group->update(Group::mapToDbAttributes($validated));

        return response()->json(['group' => $group->fresh()]);
    }

    public function destroy(string $code)
    {
        Group::findOrFail($code)->delete();

        return response()->json(['message' => 'Đã xóa nhóm']);
    }

    public function assignMember(Request $request, string $memberCode)
    {
        Member::findOrFail($memberCode);
        $validated = $request->validate([
            'group_code' => 'nullable|exists:groups,group_code',
        ]);

        DB::transaction(function () use ($memberCode, $validated) {
            GroupMembershipService::assign($memberCode, $validated['group_code'] ?? null);
        });

        return response()->json(Group::orderBy('group_created_at')->get());
    }
}
