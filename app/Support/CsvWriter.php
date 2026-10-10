<?php

namespace App\Support;

class CsvWriter
{
    /**
     * Render a CSV document; text cells that a spreadsheet would run as a formula get a leading apostrophe.
     *
     * @param  list<string>  $header
     * @param  iterable<int, list<bool|int|float|string|null>>  $rows
     */
    public function render(array $header, iterable $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, $header, ',', '"', '', "\n");

        foreach ($rows as $row) {
            fputcsv($stream, array_map($this->cell(...), $row), ',', '"', '', "\n");
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function cell(bool|int|float|string|null $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_string($value) && $value !== '' && str_contains("=+-@\t\r", $value[0]) => "'".$value,
            default => (string) $value,
        };
    }
}
