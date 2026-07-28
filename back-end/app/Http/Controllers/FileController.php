<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
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
            'target_type' => 'required|string|in:Project,Task',
            'target_code' => 'required|string',
            'user_code' => 'nullable|exists:users,user_code',
        ]);

        $targetExists = $validated['target_type'] === 'Project'
            ? Project::whereKey($validated['target_code'])->exists()
            : Task::whereKey($validated['target_code'])->exists();

        if (! $targetExists) {
            return response()->json([
                'message' => 'Dự án hoặc nhiệm vụ đính kèm không tồn tại.',
            ], 422);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = Str::uuid().'_'.$file->getClientOriginalName();
            $userCode = $validated['user_code'] ?? 'US0001';

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
                'Đã tải lên tệp: '.$attachment->attachment_file_name,
                $validated['target_type'],
                $validated['target_code']
            );

            return response()->json([
                'message' => 'Upload thành công',
                'attachment' => $attachment,
            ], 201);
        }

        return response()->json(['message' => 'Không tìm thấy file'], 400);
    }

    public function destroy(string $code)
    {
        $attachment = Attachment::findOrFail($code);
        $relativePath = ltrim(str_replace('/storage/', '', $attachment->attachment_file_path), '/');

        if ($relativePath !== '') {
            Storage::disk('public')->delete($relativePath);
        }

        $attachment->delete();

        return response()->json(['message' => 'Đã xóa tệp đính kèm']);
    }
}
