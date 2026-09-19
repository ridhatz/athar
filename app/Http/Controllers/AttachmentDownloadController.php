<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to read an uploaded file. Files live on a private disk; access is decided
 * per request by the policy, and the file is always sent as a download (never rendered in the
 * browser) with sniffing disabled, so an uploaded file cannot run as a page.
 */
class AttachmentDownloadController extends Controller
{
    public function __invoke(Request $request, ServiceRequest $serviceRequest, int $attachment): StreamedResponse
    {
        // Scoped to this request: an id from another request is simply "not found".
        $file = $serviceRequest->attachments()->findOrFail($attachment);

        // Hidden from this viewer (e.g. an internal file) reads as "not found", not "forbidden".
        abort_unless(Gate::forUser($request->user())->allows('downloadAttachment', [$serviceRequest, $file]), 404);

        $disk = Storage::disk((string) config('athar.attachments.disk'));

        abort_unless($disk->exists($file->stored_path), 404);

        return $disk->download($file->stored_path, $file->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
