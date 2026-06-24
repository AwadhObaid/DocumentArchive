<?php

namespace App\Http\Controllers;

use App\Models\DocumentAttachment;
use Illuminate\Support\Facades\Storage;

class AttachmentPreviewController extends Controller
{
    public function preview(DocumentAttachment $attachment)
    {
        $attachment->load('document');

        return view('attachments.preview', compact('attachment'));
    }

    public function data(DocumentAttachment $attachment)
    {
        $disk = Storage::disk($attachment->disk);

        if (!$disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $extension = strtolower(
            $attachment->extension ?: pathinfo($attachment->original_name, PATHINFO_EXTENSION)
        );

        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => $attachment->mime_type ?: 'application/octet-stream',
        };

        /*
        |--------------------------------------------------------------------------
        | مهم
        |--------------------------------------------------------------------------
        | نعيد الملف كـ JSON Base64 بدلاً من application/pdf مباشرة.
        | هذا يمنع برامج التحميل أو إعدادات المتصفح من التقاط رابط المعاينة
        | وإجبار المستخدم على تنزيل الملف.
        */
        $binaryContent = $disk->get($attachment->file_path);

        return response()->json([
            'file_name' => $attachment->original_name ?: $attachment->file_name,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size' => $attachment->file_size,
            'base64' => base64_encode($binaryContent),
        ]);
    }
}
