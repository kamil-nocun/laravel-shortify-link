<?php

namespace App\Http\Controllers;

use App\Support\PublicPageData;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Show the public home page with the packages on offer and the legal documents for the footer.
     */
    public function __invoke(): Response
    {
        return Inertia::render('FrontApp', [
            'packages' => PublicPageData::packages(),
            'legalDocuments' => PublicPageData::legalDocuments(),
        ]);
    }
}
