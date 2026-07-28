<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use ZipArchive;

class DownloadController extends Controller
{
    public function downloadArchive(Request $request)
    {
        $request->validate([
            'target_type' => 'required|string',
            'target_code' => 'required|string',
            'file_name' => 'required|string',
        ]);

        $targetType = $request->target_type;
        $targetCode = $request->target_code;
        $fileName = $request->file_name;
        $format = $request->format ?? '.zip';

        if (! in_array($format, ['.zip', '.tar', '.tar.gz'])) {
            $format = '.zip';
        }

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

        $tempBasePath = tempnam(sys_get_temp_dir(), 'archive_');
        if ($tempBasePath === false) {
            return response()->json(['message' => 'Không thể tạo tệp tạm'], 500);
        }
        File::delete($tempBasePath);

        if ($format === '.zip') {
            $tempPath = $tempBasePath.'.zip';
            $zip = new ZipArchive;
            if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($attachments as $attachment) {
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
                foreach ($attachments as $attachment) {
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

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
