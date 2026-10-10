<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\AccountExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PrivacyExportController extends Controller
{
    /**
     * Download the signed-in user's data as a ZIP of CSV files.
     */
    public function __invoke(Request $request, AccountExport $export): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'export');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($export->files($request->user()) as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return response()->download($path, 'shortify-export-'.now()->toDateString().'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
