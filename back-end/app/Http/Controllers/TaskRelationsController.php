<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskWatcher;
use App\Services\AccessService;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskRelationsController extends Controller
{
    public function syncDependencies(Request $request, string $taskCode)
    {
        $task = Task::with('project')->findOrFail($taskCode);
        AccessService::authorize(
            AccessService::canManageTask($request->user(), $task),
            'Chỉ người quản lý nhiệm vụ mới được thay đổi quan hệ phụ thuộc.'
        );

        $validated = $request->validate([
            'dependency_ids' => 'present|array|max:30',
            'dependency_ids.*' => 'string|distinct|exists:tasks,task_code',
        ]);

        $dependencyIds = collect($validated['dependency_ids']);
        if ($dependencyIds->contains($taskCode)) {
            return response()->json(['message' => 'Nhiệm vụ không thể phụ thuộc vào chính nó.'], 422);
        }

        $dependencies = Task::whereIn('task_code', $dependencyIds)->get();
        if ($dependencies->contains(fn (Task $dependency) => $dependency->task_project_code !== $task->task_project_code)) {
            return response()->json(['message' => 'Các nhiệm vụ phụ thuộc phải thuộc cùng một dự án.'], 422);
        }

        foreach ($dependencyIds as $dependencyId) {
            if ($this->wouldCreateCycle($taskCode, $dependencyId)) {
                return response()->json(['message' => 'Quan hệ này tạo vòng lặp phụ thuộc.'], 422);
            }
        }

        DB::transaction(function () use ($taskCode, $dependencyIds) {
            TaskDependency::where('dependency_task_code', $taskCode)->delete();
            foreach ($dependencyIds as $dependencyId) {
                TaskDependency::create([
                    'dependency_task_code' => $taskCode,
                    'dependency_depends_on_code' => $dependencyId,
                ]);
            }
        });

        ActivityService::log(
            $request->user()->user_code,
            'cập nhật quan hệ phụ thuộc',
            'Task',
            $taskCode,
            'Nhiệm vụ đang phụ thuộc vào '.$dependencyIds->count().' nhiệm vụ khác.'
        );

        return response()->json([
            'message' => 'Đã cập nhật quan hệ phụ thuộc.',
            'dependencies' => $task->fresh()->dependencies()->get(),
        ]);
    }

    public function toggleWatcher(Request $request, string $taskCode)
    {
        $task = Task::with('project')->findOrFail($taskCode);
        AccessService::authorize(AccessService::canViewTask($request->user(), $task));

        $existing = TaskWatcher::where('watcher_task_code', $taskCode)
            ->where('watcher_user_code', $request->user()->user_code)
            ->first();

        if ($existing) {
            $existing->delete();
            $watching = false;
        } else {
            TaskWatcher::create([
                'watcher_task_code' => $taskCode,
                'watcher_user_code' => $request->user()->user_code,
            ]);
            $watching = true;
        }

        ActivityService::log(
            $request->user()->user_code,
            $watching ? 'theo dõi nhiệm vụ' : 'bỏ theo dõi nhiệm vụ',
            'Task',
            $taskCode
        );

        return response()->json(['watching' => $watching]);
    }

    private function wouldCreateCycle(string $taskCode, string $dependencyId): bool
    {
        $seen = [];
        $stack = [$dependencyId];
        while ($stack) {
            $current = array_pop($stack);
            if ($current === $taskCode) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $stack = array_merge($stack, TaskDependency::where('dependency_task_code', $current)->pluck('dependency_depends_on_code')->all());
        }

        return false;
    }
}
