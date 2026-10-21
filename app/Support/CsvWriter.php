<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV en flux (mémoire constante), compatible Excel (BOM UTF-8, séparateur « ; »),
 * protégé contre l'injection de formules (= + - @ en début de cellule).
 */
final class CsvWriter
{
    /**
     * @param  list<string>  $headers
     * @param  callable(): iterable<array>  $rows
     */
    public static function download(string $filename, array $headers, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';', escape: '');

            foreach ($rows() as $row) {
                fputcsv($out, array_map(self::sanitize(...), $row), ';', escape: '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function sanitize(mixed $value): string
    {
        $value = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'oui' : 'non',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \BackedEnum => (string) $value->value,
            default => (string) $value,
        };

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
