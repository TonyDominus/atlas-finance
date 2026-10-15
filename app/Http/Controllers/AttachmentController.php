<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use OwnsFinancialRecords;

    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->owned($request, $attachment);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
