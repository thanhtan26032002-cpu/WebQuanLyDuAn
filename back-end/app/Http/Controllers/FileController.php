<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Activity;
use Illuminate\Http\Request;
use App\Services\ActivityService;

class FileController extends Controller
{
    // Upload file
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'target_type' => 'required|string',
            'target_code' => 'required|string',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            
            // Lưu file vào thư mục storage/app/public/attachments
            $path = $file->storeAs('attachments', $fileName, 'public');

            // Tạo bản ghi trong DB
            $attachment = Attachment::create([
                'attachment_file_name' => $file->getClientOriginalName(),
                'attachment_file_path' => '/storage/' . $path,
                'attachment_mime_type' => $file->getClientMimeType(),
                'attachment_size_bytes' => $file->getSize(),
                'attachment_target_type' => $request->target_type,
                'attachment_target_code' => $request->target_code,
                'attachment_uploaded_by' => 'US0001', 
            ]);

            ActivityService::log(
                'US0001',
                'Đã tải lên tệp: ' . $attachment->attachment_file_name,
                $request->target_type,
                $request->target_code
            );

            return response()->json([
                'message' => 'Upload thành công',
                'attachment' => $attachment
            ], 201);
        }

        return response()->json(['message' => 'Không tìm thấy file'], 400);
    }
}
