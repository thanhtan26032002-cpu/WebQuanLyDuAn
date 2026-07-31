<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskWorkLog;
use App\Services\AccessService;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskProgressController extends Controller
{
    public function storeChecklist(Request $request, string $taskCode)
    {
        $task = Task::with('project')->findOrFail($taskCode);
        AccessService::authorize(AccessService::canContributeToTask($request->user(), $task));
        $validated = $request->validate([
            'text' => 'required|string|max:255',
        ]);

        $checklist = $task->checklists()->create([
            'checklist_text' => trim($validated['text']),
            'checklist_is_completed' => false,
        ]);

        return response()->json([
            'message' => 'Đã thêm công việc con.',
            'checklist' => $checklist,
            'progress' => $this->syncTaskProgress($task),
        ], 201);
    }

    public function updateChecklist(Request $request, string $taskCode, string $checklistCode)
    {
        $task = Task::with('project')->findOrFail($taskCode);
        AccessService::authorize(AccessService::canContributeToTask($request->user(), $task));
        $checklist = $task->checklists()->whereKey($checklistCode)->firstOrFail();
        $validated = $request->validate([
            'text' => 'sometimes|required|string|max:255',
            'completed' => 'sometimes|required|boolean',
        ]);

        if (array_key_exists('text', $validated)) {
            $checklist->checklist_text = trim($validated['text']);
        }
        if (array_key_exists('completed', $validated)) {
            $checklist->checklist_is_completed = $validated['completed'];
        }
        $checklist->save();

        return response()->json([
            'message' => 'Đã cập nhật công việc con.',
            'checklist' => $checklist,
            'progress' => $this->syncTaskProgress($task),
        ]);
    }

    public function destroyChecklist(Request $request, string $taskCode, string $checklistCode)
    {
        $task = Task::with('project')->findOrFail($taskCode);
        AccessService::authorize(AccessService::canContributeToTask($request->user(), $task));
        $checklist = $task->checklists()->whereKey($checklistCode)->firstOrFail();
        $checklist->delete();

        return response()->json([
            'message' => 'Đã xóa công việc con.',
            'progress' => $this->syncTaskProgress($task),
        ]);
    }

    public function storeWorkLog(Request $request, string $taskCode)
    {
        $task = Task::with('project')->findOrFail($taskCode);
        AccessService::authorize(AccessService::canContributeToTask($request->user(), $task));

        if (! $task->task_assignee_code) {
            return response()->json([
                'message' => 'Nhiệm vụ phải có người phụ trách trước khi báo cáo tiến độ.',
                'errors' => [
                    'assignee_code' => ['Vui lòng phân công người phụ trách cho nhiệm vụ.'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'note' => 'nullable|string|max:5000',
            'checklist_ids' => 'sometimes|array',
            'checklist_ids.*' => 'string|distinct',
            'files' => 'sometimes|array|max:10',
            'files.*.code' => 'nullable|string|max:50',
            'files.*.name' => 'required_with:files|string|max:255',
            'files.*.size' => 'nullable|string|max:50',
            'files.*.url' => 'nullable|string|max:1000',
            'files.*.uploadedBy' => 'nullable|string|max:50',
            'files.*.uploadedAt' => 'nullable|string|max:100',
        ]);

        $checklistIds = $validated['checklist_ids'] ?? [];
        $checklists = TaskChecklist::where('checklist_task_code', $taskCode)
            ->whereIn('checklist_code', $checklistIds)
            ->get();

        if ($checklists->count() !== count($checklistIds)) {
            return response()->json([
                'message' => 'Có công việc con không thuộc nhiệm vụ này.',
                'errors' => ['checklist_ids' => ['Danh sách công việc con không hợp lệ.']],
            ], 422);
        }

        $completedItems = $checklists->map(fn (TaskChecklist $item) => [
            'id' => $item->checklist_code,
            'text' => $item->checklist_text,
        ])->values()->all();

        $reporterCode = $request->user()->user_code;
        $workLog = DB::transaction(function () use ($task, $validated, $checklists, $completedItems, $reporterCode) {
            $workLog = TaskWorkLog::create([
                'worklog_task_code' => $task->task_code,
                'worklog_reporter_code' => $reporterCode,
                'worklog_time' => $validated['time'],
                'worklog_duration_minutes' => $validated['duration_minutes'] ?? null,
                'worklog_note' => $validated['note'] ?? null,
                'worklog_date' => now()->toDateString(),
                'worklog_completed_items' => $completedItems,
                'worklog_files' => $validated['files'] ?? [],
            ]);

            if ($checklists->isNotEmpty()) {
                TaskChecklist::whereIn('checklist_code', $checklists->pluck('checklist_code'))
                    ->update(['checklist_is_completed' => true]);
            }

            $this->syncTaskProgress($task);

            return $workLog;
        });

        ActivityService::log(
            $request->user()->user_code,
            'báo cáo tiến độ',
            'Task',
            $task->task_code,
            'Đã thêm báo cáo tiến độ cho nhiệm vụ: '.$task->task_title
        );

        $workLog->load('reporter');
        $task->refresh()->load('checklists');

        return response()->json([
            'message' => 'Đã lưu báo cáo tiến độ.',
            'work_log' => $workLog,
            'checklists' => $task->checklists,
            'progress' => $task->task_progress,
        ], 201);
    }

    private function syncTaskProgress(Task $task): int
    {
        $total = $task->checklists()->count();
        $completed = $total
            ? $task->checklists()->where('checklist_is_completed', true)->count()
            : 0;
        $progress = $total ? (int) round(($completed / $total) * 100) : 0;

        $task->update(['task_progress' => $progress]);

        return $progress;
    }
}
