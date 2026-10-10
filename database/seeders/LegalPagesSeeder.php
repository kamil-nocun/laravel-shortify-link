<?php

namespace Database\Seeders;

use App\Actions\SavePageVersion;
use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class LegalPagesSeeder extends Seeder
{
    private const LOREM = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.';

    /**
     * Create the published GDPR pages with placeholder content; existing pages are left untouched.
     */
    public function run(SavePageVersion $savePageVersion): void
    {
        foreach (['privacy-policy' => 'Privacy policy', 'cookies-policy' => 'Cookies policy'] as $slug => $title) {
            $page = Page::firstOrCreate(['slug' => $slug], ['status' => PageStatus::Published]);

            if ($page->wasRecentlyCreated) {
                $savePageVersion($page, $title, '<h2>'.$title.'</h2><p>'.self::LOREM.'</p><p>'.self::LOREM.'</p>', null);
            }
        }
    }
}
