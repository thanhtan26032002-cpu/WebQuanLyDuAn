<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Services\AccessService;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use ZipArchive;

class DownloadController extends Controller
{
    public function downloadAttachment(Request $request, string $code)
    {
        $attachment = Attachment::findOrFail($code);
        $target = $attachment->attachment_target_type === 'Project'
            ? Project::findOrFail($attachment->attachment_target_code)
            : Task::with('project')->findOrFail($attachment->attachment_target_code);

        AccessService::authorize(
            $target instanceof Project
                ? AccessService::isProjectParticipant($request->user(), $target)
                : ($attachment->attachment_target_type === 'TaskComment'
                    ? AccessService::canViewTask($request->user(), $target)
                    : AccessService::isTaskParticipant($request->user(), $target))
        );

        $relativePath = ltrim(str_replace('/storage/', '', $attachment->attachment_file_path), '/');
        abort_unless($relativePath !== '' && Storage::disk('public')->exists($relativePath), 404, 'Tệp không còn tồn tại trên hệ thống.');

        ActivityService::log(
            $request->user()->user_code,
            'tải tệp xuống',
            $attachment->attachment_target_type,
            $attachment->attachment_target_code,
            'Đã tải xuống tệp: '.$attachment->attachment_file_name
        );

        return Storage::disk('public')->download($relativePath, $attachment->attachment_file_name);
    }

    public function downloadArchive(Request $request)
    {
        $validated = $request->validate([
            'target_type' => ['required', Rule::in(['Project', 'Task'])],
            'target_code' => 'required|string',
            'file_name' => 'required|string|max:120',
            'format' => ['nullable', Rule::in(['.zip', '.tar', '.tar.gz'])],
        ]);

        $targetType = $validated['target_type'];
        $targetCode = $validated['target_code'];
        $fileName = preg_replace('/[^\pL\pN _.-]+/u', '', $validated['file_name']) ?: 'tai-lieu';
        $format = $validated['format'] ?? '.zip';

        $target = $targetType === 'Project'
            ? Project::findOrFail($targetCode)
            : Task::with('project')->findOrFail($targetCode);
        AccessService::authorize(
            $target instanceof Project
                ? AccessService::isProjectParticipant($request->user(), $target)
                : AccessService::isTaskParticipant($request->user(), $target)
        );

        // Remove any extension from input file_name to avoid double extensions
        $fileName = preg_replace('/(\.zip|\.tar|\.tar\.gz)$/i', '', $fileName);
        $fileName .= $format;

        // Fetch attachments
        $attachments = Attachment::where('attachment_target_type', $targetType)
            ->where('attachment_target_code', $targetCode)
            ->get();

        if ($attachments->isEmpty()) {
            return response()->json(['message' => 'Không có tệp nào để tải xuống'], 404);
        }

        $availableAttachments = $attachments->filter(function (Attachment $attachment) {
            $relativePath = ltrim(str_replace('/storage/', '', $attachment->attachment_file_path), '/');

            return $relativePath !== '' && Storage::disk('public')->exists($relativePath);
        });
        if ($availableAttachments->isEmpty()) {
            return response()->json(['message' => 'Các tệp đính kèm không còn tồn tại trên hệ thống.'], 404);
        }

        $tempBasePath = tempnam(sys_get_temp_dir(), 'archive_');
        if ($tempBasePath === false) {
            return response()->json(['message' => 'Không thể tạo tệp tạm'], 500);
        }
        File::delete($tempBasePath);

        if ($format === '.zip') {
            $tempPath = $tempBasePath.'.zip';
            $zip = new ZipArchive;
            if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($availableAttachments as $attachment) {
                    $relativePath = str_replace('/storage/', '', $attachment->file_path);
                    $absolutePath = storage_path('app/public/'.$relativePath);
                    if (File::exists($absolutePath)) {
                        $zip->addFile($absolutePath, $attachment->file_name);
                    }
                }
                $zip->close();
            } else {
                return response()->json(['message' => 'Lỗi khi tạo file ZIP'], 500);
            }
        } else {
            // .tar or .tar.gz
            $tempPathTar = $tempBasePath.'.tar';
            try {
                $phar = new \PharData($tempPathTar);
                foreach ($availableAttachments as $attachment) {
                    $relativePath = str_replace('/storage/', '', $attachment->file_path);
                    $absolutePath = storage_path('app/public/'.$relativePath);
                    if (File::exists($absolutePath)) {
                        $phar->addFile($absolutePath, $attachment->file_name);
                    }
                }
                if ($format === '.tar.gz') {
                    $phar->compress(\Phar::GZ);
                    $tempPath = $tempPathTar.'.gz';
                    // Clean up original tar
                    if (File::exists($tempPathTar)) {
                        unlink($tempPathTar);
                    }
                } else {
                    $tempPath = $tempPathTar;
                }
            } catch (\Exception $e) {
                return response()->json(['message' => 'Lỗi khi tạo file TAR: '.$e->getMessage()], 500);
            }
        }

        ActivityService::log(
            $request->user()->user_code,
            'tải bộ tệp xuống',
            $targetType,
            $targetCode,
            'Đã tải xuống '.$availableAttachments->count().' tệp dưới dạng '.$format.': '.$fileName
        );

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
