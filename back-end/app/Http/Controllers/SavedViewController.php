<?php

namespace App\Http\Controllers;

use App\Models\SavedView;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->savedViews()->orderByDesc('view_is_favorite')->orderBy('view_name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'scope' => 'nullable|in:tasks,projects',
            'filters' => 'required|array',
            'is_favorite' => 'sometimes|boolean',
        ]);
        $view = SavedView::create([
            'view_user_code' => $request->user()->user_code,
            'view_name' => trim($validated['name']),
            'view_scope' => $validated['scope'] ?? 'tasks',
            'view_filters' => $validated['filters'],
            'view_is_favorite' => $validated['is_favorite'] ?? false,
        ]);

        return response()->json(['message' => 'Đã lưu chế độ xem.', 'view' => $view], 201);
    }

    public function update(Request $request, string $code)
    {
        $view = $request->user()->savedViews()->whereKey($code)->firstOrFail();
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'filters' => 'sometimes|required|array',
            'is_favorite' => 'sometimes|boolean',
        ]);
        $view->update(SavedView::mapToDbAttributes($validated));

        return response()->json(['message' => 'Đã cập nhật chế độ xem.', 'view' => $view->fresh()]);
    }

    public function destroy(Request $request, string $code)
    {
        $request->user()->savedViews()->whereKey($code)->firstOrFail()->delete();

        return response()->json(['message' => 'Đã xóa chế độ xem.']);
    }
}
