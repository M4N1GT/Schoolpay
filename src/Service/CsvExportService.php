<?php

namespace App\Service;

class CsvExportService
{
    public function build(array $rows, array $headers): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }
}
