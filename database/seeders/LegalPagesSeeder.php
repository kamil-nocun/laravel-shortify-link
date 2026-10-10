<?php

namespace Database\Seeders;

use App\Actions\SavePageVersion;
use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class LegalPagesSeeder extends Seeder
{
    private const SENTENCES = [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
        'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.',
        'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
        'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam.',
        'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos.',
        'Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit.',
        'Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur.',
        'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti.',
        'Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint.',
    ];

    private const HEADINGS = [
        'Lorem ipsum dolor sit amet', 'Consectetur adipiscing elit', 'Sed do eiusmod tempor', 'Ut enim ad minim veniam',
        'Duis aute irure dolor', 'Excepteur sint occaecat', 'Nemo enim ipsam voluptatem', 'Neque porro quisquam',
        'Quis autem vel eum iure', 'At vero eos et accusamus', 'Temporibus autem quibusdam', 'Itaque earum rerum hic tenetur',
    ];

    /**
     * Section counts and the 1-based sections that carry a bullet list or the cookie table.
     */
    private const DOCUMENTS = [
        'privacy-policy' => ['title' => 'Privacy policy', 'sections' => 10, 'lists' => [2, 5, 8], 'table' => null],
        'cookies-policy' => ['title' => 'Cookies policy', 'sections' => 6, 'lists' => [5], 'table' => 3],
        'terms' => ['title' => 'Terms of service', 'sections' => 12, 'lists' => [3, 7, 10], 'table' => null],
    ];

    /**
     * Create the published GDPR pages with placeholder content; existing pages are left untouched.
     */
    public function run(SavePageVersion $savePageVersion): void
    {
        foreach (self::DOCUMENTS as $slug => $document) {
            $page = Page::firstOrCreate(['slug' => $slug], ['status' => PageStatus::Published, 'is_legal_document' => true]);

            if ($page->wasRecentlyCreated) {
                $savePageVersion($page, $document['title'], $this->render($document), null);
            }
        }
    }

    /**
     * @param  array{sections: int, lists: list<int>, table: int|null}  $document
     */
    private function render(array $document): string
    {
        $html = '';

        for ($section = 1; $section <= $document['sections']; $section++) {
            $html .= '<h2>'.$section.'. '.self::HEADINGS[($section - 1) % count(self::HEADINGS)].'</h2>';
            $html .= $this->paragraph($section * 3).$this->paragraph($section * 3 + 4);

            if (in_array($section, $document['lists'], true)) {
                $html .= $this->list($section);
            }

            if ($document['table'] === $section) {
                $html .= $this->cookieTable();
            }
        }

        return $html;
    }

    private function paragraph(int $offset): string
    {
        $sentences = array_map(fn (int $index) => self::SENTENCES[$index % count(self::SENTENCES)], range($offset, $offset + 3));

        return '<p>'.implode(' ', $sentences).'</p>';
    }

    private function list(int $section): string
    {
        $items = array_map(fn (int $index) => '<li>'.self::SENTENCES[($section + $index) % count(self::SENTENCES)].'</li>', range(0, 3));

        return '<ul>'.implode('', $items).'</ul>';
    }

    private function cookieTable(): string
    {
        $rows = '';

        foreach (['session', 'consent', 'preferences', 'analytics'] as $index => $name) {
            $rows .= '<tr><td>lorem_'.$name.'</td><td>'.self::SENTENCES[$index].'</td><td>'.(($index + 1) * 30).' days</td></tr>';
        }

        return '<table><tbody><tr><th>Name</th><th>Purpose</th><th>Expiry</th></tr>'.$rows.'</tbody></table>';
    }
}
