<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\AccountExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class PrivacyExportController extends Controller
{
    /**
     * Download the signed-in user's data as a ZIP of CSV files.
     */
    public function __invoke(Request $request, AccountExport $export): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'export');

        try {
            $zip = new ZipArchive;
            abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 500);

            foreach ($export->files($request->user()) as $name => $content) {
                $zip->addFromString($name, $content);
            }

            abort_unless($zip->close(), 500);
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception;
        }

        return response()->download($path, 'shortify-export-'.now()->toDateString().'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
