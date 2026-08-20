<?php

namespace App\Http\Controllers;

use App\Models\ReportAttachment;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(ReportAttachment $attachment)
    {
        $this->authorize('download', $attachment);
        if (! Storage::disk('private')->exists($attachment->file_path)) {
            return back()->withErrors(['attachment' => 'File lampiran tidak ditemukan di server. Silakan hubungi admin untuk pengecekan arsip.']);
        }

        return Storage::disk('private')->download($attachment->file_path, $attachment->original_name);
    }
}
