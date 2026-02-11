<?php

namespace WPSSC\Services;

use WPSSC\Repositories\ReservationRepository;

if (!defined('ABSPATH')) { exit; }

final class PaymentReconciliationService
{
    /**
     * Processa files del parser i retorna resultats.
     *
     * Resultats per fila:
     * - ok_paid
     * - already_paid
     * - not_found
     * - ambiguous_token
     * - no_token
     * - expired_needs_review
     * - not_reserved (cancelled/other)
     * - error
     */
    public function reconcile(array $parsed_rows): array
    {
        $repo = new ReservationRepository();

        $results = [];
        $seenTokens = [];

        foreach ($parsed_rows as $row) {
            $rowNo = (int)($row['row'] ?? 0);
            $tokens = is_array($row['tokens'] ?? null) ? $row['tokens'] : [];
            $amount = $row['amount'] ?? null;

            if (count($tokens) === 0) {
                $results[] = $this->res($rowNo, 'no_token', null, $amount, __('No token detected', 'wp-simple-stock-checkout'));
                continue;
            }

            if (count($tokens) > 1) {
                $results[] = $this->res($rowNo, 'ambiguous_token', null, $amount, __('Multiple tokens detected in same row', 'wp-simple-stock-checkout'));
                continue;
            }

            $token = (string)$tokens[0];

            // Idempotència per CSV amb tokens repetits
            if (isset($seenTokens[$token])) {
                $results[] = $this->res($rowNo, 'duplicate_in_csv', $token, $amount, __('Token repeated in CSV', 'wp-simple-stock-checkout'));
                continue;
            }
            $seenTokens[$token] = true;

            try {
                $r = $repo->mark_paid_by_token($token);

                // r pot ser un string status intern
                $results[] = $this->res($rowNo, $r, $token, $amount);

            } catch (\Throwable $e) {
                $results[] = $this->res($rowNo, 'error', $token, $amount, $e->getMessage());
            }
        }

        return $results;
    }

    private function res(int $row, string $status, ?string $token, $amount, string $message = ''): array
    {
        return [
            'row'     => $row,
            'status'  => $status,
            'token'   => $token,
            'amount'  => $amount,
            'message' => $message,
        ];
    }
}
