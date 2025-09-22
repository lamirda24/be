<?php

namespace App\Http\Controllers;

use App\Models\File;

class DownloadLogController extends Controller
{
    public function index($id)
    {
        $file = File::findOrFail($id);
        return $file->downloadLogs()->latest('downloaded_at')->paginate(50);
    }
}
