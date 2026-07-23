<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Activity;
use Illuminate\Http\Request;

class FileController extends Controller
{
    // Upload file
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'target_type' => 'required|string',
            'target_id' => 'required|integer',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            
            // Lưu file vào thư mục storage/app/public/attachments
            $path = $file->storeAs('attachments', $fileName, 'public');

            // Tạo bản ghi trong DB
            $attachment = Attachment::create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => '/storage/' . $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'target_type' => $request->target_type,
                'target_id' => $request->target_id,
                'uploaded_by' => 1, // Mock user_id = 1
            ]);

            // Ghi log activity
            Activity::create([
                'user_id' => 1,
                'action' => 'Đã tải lên tệp: ' . $attachment->file_name,
                'target_type' => $request->target_type,
                'target_id' => $request->target_id,
            ]);

            return response()->json([
                'message' => 'Upload thành công',
                'attachment' => $attachment
            ], 201);
        }

        return response()->json(['message' => 'Không tìm thấy file'], 400);
    }
}
