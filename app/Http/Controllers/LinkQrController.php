<?php

namespace App\Http\Controllers;

use App\Enums\LinkEntry;
use App\Models\Link;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LinkQrController extends Controller
{
    /**
     * Render the QR code of the link's code or alias address as SVG; scans are marked with ?qr=1.
     */
    public function __invoke(Request $request, Link $link, string $type): Response
    {
        Gate::authorize('update', $link);

        $entry = LinkEntry::from(ucfirst($type));
        $url = $link->qrUrl($entry);

        abort_if($url === null, 404);

        $headers = ['Content-Type' => 'image/svg+xml'];

        if ($request->boolean('download')) {
            $name = $entry === LinkEntry::Code ? $link->code : $link->alias;
            $headers['Content-Disposition'] = 'attachment; filename="'.$name.'.svg"';
        }

        return response((new QRCode(new QROptions(['outputBase64' => false])))->render($url), 200, $headers);
    }
}
