<?php
// app/Http/Controllers/FileSignedController.php
namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class FileSignedController extends Controller
{
    // FE asks for a signed URL; add query "inline=1" to request inline rendering.
    public function issue(Request $request, string $slug)
    {
        $file = File::where('slug', $slug)->firstOrFail();

        $inline = (bool) $request->boolean('inline'); // ?inline=1
        $url = URL::temporarySignedRoute(
            'files.signed-download',
            now()->addMinutes(5),
            [
                'path'     => $file->storage_path,                     // e.g. "files/abc.pdf"
                'filename' => $file->original_name ?: basename($file->storage_path),
                'inline'   => $inline ? 1 : 0,                        // pass through
            ]
        );

        // optional audit
        if (method_exists($file, 'recordDownload')) {
            $file->recordDownload($request->ip(), $request->userAgent());
        }

        return response()->json([
            'status'  => true,
            'message' => 'Signed URL issued',
            'data'    => ['url' => $url, 'expires_in' => 300],
        ]);
    }

    // GET /api/files/signed-download?...&signature=...
    public function download(Request $request)
    {
        $relative = ltrim((string) $request->query('path', ''), '/');
        $filename = (string) $request->query('filename', basename($relative));
        $inline   = (bool) $request->boolean('inline');

        $abs  = storage_path('app/public/' . $relative);
        abort_unless(is_file($abs), 404);

        // detect mime
        $mime = @mime_content_type($abs) ?: 'application/octet-stream';

        // what browsers can usually render inline
        $inlineable = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'text/plain',
            'text/csv',
            'text/html',
            'audio/mpeg',
            'audio/ogg',
            'video/mp4',
            'video/webm',
        ];

        $shouldInline = $inline && in_array($mime, $inlineable, true);

        if ($shouldInline) {
            // OPEN IN BROWSER
            return response()->file($abs, [
                'Content-Type'        => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            ]);
        }

        // FORCE DOWNLOAD
        return response()->download($abs, $filename, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
        ]);
    }
}
