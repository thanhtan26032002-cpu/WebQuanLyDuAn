<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Services\AccessService;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    // Upload file
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'target_type' => 'required|string|in:Project,Task,TaskComment',
            'target_code' => 'required|string',
        ]);

        $target = $validated['target_type'] === 'Project'
            ? Project::whereKey($validated['target_code'])->first()
            : Task::with('project')->whereKey($validated['target_code'])->first();

        if (! $target) {
            return response()->json([
                'message' => 'Dự án hoặc nhiệm vụ đính kèm không tồn tại.',
            ], 422);
        }
        $canUpload = $target instanceof Project
            ? AccessService::canManageProject($request->user(), $target)
            : ($validated['target_type'] === 'TaskComment'
                ? AccessService::canViewTask($request->user(), $target)
                : AccessService::canContributeToTask($request->user(), $target));
        AccessService::authorize($canUpload, 'Bạn không có quyền tải tệp trực tiếp lên nhiệm vụ này.');

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = Str::uuid().'_'.$file->getClientOriginalName();
            $userCode = $request->user()->user_code;

            // Lưu file vào thư mục storage/app/public/attachments
            $path = $file->storeAs('attachments', $fileName, 'public');

            // Tạo bản ghi trong DB
            $attachment = Attachment::create([
                'attachment_file_name' => $file->getClientOriginalName(),
                'attachment_file_path' => '/storage/'.$path,
                'attachment_mime_type' => $file->getClientMimeType(),
                'attachment_size_bytes' => $file->getSize(),
                'attachment_target_type' => $validated['target_type'],
                'attachment_target_code' => $validated['target_code'],
                'attachment_uploaded_by' => $userCode,
            ]);

            ActivityService::log(
                $userCode,
                'tải tệp lên',
                $validated['target_type'],
                $validated['target_code'],
                'Đã tải lên tệp: '.$attachment->attachment_file_name
            );

            return response()->json([
                'message' => 'Upload thành công',
                'attachment' => $attachment,
            ], 201);
        }

        return response()->json(['message' => 'Không tìm thấy file'], 400);
    }

    public function destroy(Request $request, string $code)
    {
        $attachment = Attachment::findOrFail($code);
        $target = $attachment->attachment_target_type === 'Project'
            ? Project::find($attachment->attachment_target_code)
            : Task::with('project')->find($attachment->attachment_target_code);
        $canViewTarget = $target instanceof Project
            ? AccessService::canViewProject($request->user(), $target)
            : ($target instanceof Task && AccessService::canViewTask($request->user(), $target));
        $canManageTarget = ($target instanceof Project && AccessService::canManageProject($request->user(), $target))
            || ($target instanceof Task && AccessService::canManageTask($request->user(), $target));
        AccessService::authorize(
            AccessService::isAdmin($request->user())
            || $canManageTarget
            || ($canViewTarget && $attachment->attachment_uploaded_by === $request->user()->user_code)
        );
        $relativePath = ltrim(str_replace('/storage/', '', $attachment->attachment_file_path), '/');

        if ($relativePath !== '') {
            Storage::disk('public')->delete($relativePath);
        }

        ActivityService::log(
            $request->user()->user_code,
            'xóa tệp đính kèm',
            $attachment->attachment_target_type,
            $attachment->attachment_target_code,
            'Đã xóa tệp: '.$attachment->attachment_file_name
        );
        $attachment->delete();

        return response()->json(['message' => 'Đã xóa tệp đính kèm']);
    }
}
