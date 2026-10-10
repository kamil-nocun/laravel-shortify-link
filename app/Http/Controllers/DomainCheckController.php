<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DomainCheckController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('shortify.domain_check_secret');
        $provided = (string) $request->header('X-Domain-Check-Secret');

        abort_if($secret === '' || ! hash_equals($secret, $provided), 403);

        $domain = $request->query('domain');

        abort_unless(is_string($domain), 404);

        $host = mb_strtolower(trim($domain));

        abort_if($host === '' || ! Domain::active()->where('host', $host)->exists(), 404);

        return response()->noContent(200);
    }
}
