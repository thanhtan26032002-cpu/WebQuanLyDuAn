<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectNote;
use App\Services\AccessService;
use App\Services\ActivityService;
use Illuminate\Http\Request;

class ProjectNoteController extends Controller
{
    public function index(Request $request, string $projectCode)
    {
        $project = Project::findOrFail($projectCode);
        AccessService::authorize(AccessService::canViewProject($request->user(), $project));

        return response()->json(
            $project->notes()
                ->with('author:user_code,user_name,user_avatar,user_color,user_job_title,user_department')
                ->get()
        );
    }

    public function store(Request $request, string $projectCode)
    {
        $project = Project::findOrFail($projectCode);
        AccessService::authorize(
            AccessService::isProjectParticipant($request->user(), $project),
            'Chỉ thành viên tham gia dự án mới được thêm ghi chú.'
        );

        $validated = $request->validate($this->rules());
        $note = $project->notes()->create([
            'note_author_code' => $request->user()->user_code,
            'note_title' => trim($validated['title']),
            'note_content' => trim($validated['content']),
            'note_is_pinned' => (bool) ($validated['is_pinned'] ?? false),
        ]);

        ActivityService::log(
            $request->user()->user_code,
            'thêm ghi chú dự án',
            'Project',
            $projectCode,
            'Đã thêm ghi chú: '.$note->note_title
        );

        return response()->json([
            'message' => 'Đã thêm ghi chú dự án.',
            'note' => $note->load('author:user_code,user_name,user_avatar,user_color,user_job_title,user_department'),
        ], 201);
    }

    public function update(Request $request, string $projectCode, string $noteCode)
    {
        $project = Project::findOrFail($projectCode);
        $note = $project->notes()->whereKey($noteCode)->firstOrFail();
        $this->authorizeNoteChange($request, $project, $note);

        $validated = $request->validate($this->rules(true));
        $changes = [];
        if (array_key_exists('title', $validated)) {
            $changes['note_title'] = trim($validated['title']);
        }
        if (array_key_exists('content', $validated)) {
            $changes['note_content'] = trim($validated['content']);
        }
        if (array_key_exists('is_pinned', $validated)) {
            $changes['note_is_pinned'] = (bool) $validated['is_pinned'];
        }
        $note->update($changes);

        ActivityService::log(
            $request->user()->user_code,
            'cập nhật ghi chú dự án',
            'Project',
            $projectCode,
            'Đã cập nhật ghi chú: '.$note->note_title
        );

        return response()->json([
            'message' => 'Đã cập nhật ghi chú.',
            'note' => $note->fresh()->load('author:user_code,user_name,user_avatar,user_color,user_job_title,user_department'),
        ]);
    }

    public function destroy(Request $request, string $projectCode, string $noteCode)
    {
        $project = Project::findOrFail($projectCode);
        $note = $project->notes()->whereKey($noteCode)->firstOrFail();
        $this->authorizeNoteChange($request, $project, $note);
        $title = $note->note_title;
        $note->delete();

        ActivityService::log(
            $request->user()->user_code,
            'xóa ghi chú dự án',
            'Project',
            $projectCode,
            'Đã xóa ghi chú: '.$title
        );

        return response()->json(['message' => 'Đã xóa ghi chú.']);
    }

    private function authorizeNoteChange(Request $request, Project $project, ProjectNote $note): void
    {
        $isAuthor = $note->note_author_code === $request->user()->user_code;
        AccessService::authorize(
            $isAuthor || AccessService::canManageProject($request->user(), $project),
            'Bạn chỉ có thể thay đổi ghi chú do mình tạo.'
        );
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'title' => $required.'|string|max:255',
            'content' => $required.'|string|max:10000',
            'is_pinned' => 'sometimes|boolean',
        ];
    }
}
