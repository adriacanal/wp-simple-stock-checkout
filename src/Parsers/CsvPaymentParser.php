<?php

namespace WPSSC\Parsers;

if (!defined('ABSPATH')) { exit; }

final class CsvPaymentParser
{
    // UUID v4 / general UUID (36 chars)
    private const TOKEN_REGEX = '/\b[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\b/i';

    /**
     * Parseja un CSV i retorna files estructurades:
     * [
     *   ['row' => 2, 'token' => '...', 'amount' => 12.34|null, 'raw' => [...cells...]],
     *   ...
     * ]
     */
    public function parse_file(string $filepath, int $max_rows = 5000): array
    {
        if (!is_readable($filepath)) {
            throw new \RuntimeException('CSV file not readable.');
        }

        $max_rows = max(1, min(50000, (int)$max_rows));

        $delimiter = $this->detect_delimiter($filepath);
        $handle = fopen($filepath, 'rb');
        if (!$handle) {
            throw new \RuntimeException('Failed to open CSV.');
        }

        $rows = [];
        $lineNo = 0;

        while (($data = fgetcsv($handle, 0, $delimiter, '"', "\\")) !== false) {
            $lineNo++;

            // Salta línies completament buides
            if ($data === [null] || $this->is_all_empty($data)) {
                continue;
            }

            // Limitem volum
            if (count($rows) >= $max_rows) {
                break;
            }

            $cells = array_map(function ($v) {
                $v = is_string($v) ? trim($v) : (string)$v;
                // normalitza espais raros
                $v = preg_replace('/\s+/', ' ', $v);
                return $v;
            }, $data);

            $tokens = $this->extract_tokens($cells);
            $amount = $this->extract_amount($cells);

            $rows[] = [
                'row'   => $lineNo,
                'tokens'=> $tokens,      // pot ser 0, 1 o més
                'amount'=> $amount,      // optional
                'raw'   => $cells,
            ];
        }

        fclose($handle);

        return $rows;
    }

    private function detect_delimiter(string $filepath): string
    {
        $sample = file_get_contents($filepath, false, null, 0, 4096);
        if ($sample === false) return ',';

        $delims = [',', ';', "\t", '|'];
        $counts = [];
        foreach ($delims as $d) {
            $counts[$d] = substr_count($sample, $d);
        }

        arsort($counts);
        $best = array_key_first($counts);

        // si cap delimitador destaca, coma
        return $counts[$best] > 0 ? $best : ',';
    }

    private function is_all_empty(array $cells): bool
    {
        foreach ($cells as $c) {
            if (is_string($c) && trim($c) !== '') return false;
            if (!is_string($c) && (string)$c !== '') return false;
        }
        return true;
    }

    private function extract_tokens(array $cells): array
    {
        $found = [];

        foreach ($cells as $cell) {
            if (!is_string($cell) || $cell === '') continue;

            if (preg_match_all(self::TOKEN_REGEX, $cell, $m)) {
                foreach ($m[0] as $t) {
                    $found[] = strtolower($t);
                }
            }
        }

        // uniques, preserve order
        $unique = [];
        foreach ($found as $t) {
            if (!in_array($t, $unique, true)) $unique[] = $t;
        }

        return $unique;
    }

    /**
     * Heurística simple: busca un valor que sembli import.
     * Exemple: "12,34" o "12.34" o "€12,34"
     */
    private function extract_amount(array $cells): ?float
    {
        foreach ($cells as $cell) {
            if (!is_string($cell)) continue;
            $v = trim($cell);
            if ($v === '') continue;

            // elimina moneda i text
            $v = preg_replace('/[^\d\.,\-]/', '', $v);
            if ($v === '' || $v === '-' ) continue;

            // normalitza "1.234,56" i "1,234.56"
            // si té coma i punt, decidim separador decimal pel darrer que apareix
            $lastComma = strrpos($v, ',');
            $lastDot   = strrpos($v, '.');

            if ($lastComma !== false && $lastDot !== false) {
                if ($lastComma > $lastDot) {
                    // coma decimal, treu punts milers
                    $v = str_replace('.', '', $v);
                    $v = str_replace(',', '.', $v);
                } else {
                    // punt decimal, treu comes milers
                    $v = str_replace(',', '', $v);
                }
            } elseif ($lastComma !== false) {
                // assumim coma decimal
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } else {
                // només punt o cap separador
                $v = str_replace(',', '', $v);
            }

            if (!is_numeric($v)) continue;

            $f = (float)$v;

            // descarta imports absurds (heurística)
            if ($f === 0.0) continue;
            if (abs($f) > 1000000) continue;

            return $f;
        }

        return null;
    }
}
