<?php

namespace App\Services;

final class Csv
{
    /**
     * Rows of trimmed cells, skipping blank lines. Line numbers are those of the file.
     *
     * @return list<array{line: int, cells: list<string>}>
     */
    public static function rows(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $line) {
            if (trim($line) === '' || trim(str_replace(',', '', $line)) === '') {
                continue;
            }
            $rows[] = ['line' => $i + 1, 'cells' => array_map('trim', str_getcsv($line, escape: ''))];
        }

        return $rows;
    }
}
