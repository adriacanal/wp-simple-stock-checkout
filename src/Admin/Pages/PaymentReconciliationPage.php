<?php

namespace WPSSC\Admin\Pages;

use WPSSC\Parsers\CsvPaymentParser;
use WPSSC\Security\Capabilities;
use WPSSC\Services\PaymentReconciliationService;

if (!defined('ABSPATH')) { exit; }

final class PaymentReconciliationPage
{
    public const PAGE_SLUG = 'wpssc-payment-reconciliation';

    private const NONCE_ACTION = 'wpssc_reconcile_payments';
    private const NONCE_NAME   = '_wpssc_nonce';

    public function render(): void
    {
        if (!current_user_can(Capabilities::CAP_MANAGE)) {
            wp_die(__('No tens permisos per accedir aquí.', 'wp-simple-stock-checkout'));
        }

        $results = null;
        $error = null;
        $summary = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpssc_reconcile_submit'])) {
            $nonce = isset($_POST[self::NONCE_NAME]) ? (string)$_POST[self::NONCE_NAME] : '';
            if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                $error = __('Security check failed (invalid nonce).', 'wp-simple-stock-checkout');
            } else {
                try {
                    $results = $this->handle_upload_and_reconcile();
                    $summary = $this->summarize($results);
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Payment reconciliation (CSV)', 'wp-simple-stock-checkout'); ?></h1>

            <p class="description">
                <?php echo esc_html__('Puja un CSV de pagaments. El sistema buscarà el token (UUID) a qualsevol columna i marcarà la comanda com a pagada.', 'wp-simple-stock-checkout'); ?>
            </p>

            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="wpssc_reconcile_submit" value="1" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="wpssc_csv"><?php echo esc_html__('CSV file', 'wp-simple-stock-checkout'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="wpssc_csv" name="wpssc_csv" accept=".csv,text/csv" required />
                            <p class="description">
                                <?php echo esc_html__('El token ha d’aparèixer al CSV (en una columna o dins del concepte/descripció).', 'wp-simple-stock-checkout'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Process CSV', 'wp-simple-stock-checkout')); ?>
            </form>

            <?php if (is_array($summary)): ?>
                <h2><?php echo esc_html__('Summary', 'wp-simple-stock-checkout'); ?></h2>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li><?php echo esc_html('ok_paid: ' . (int)$summary['ok_paid']); ?></li>
                    <li><?php echo esc_html('already_paid: ' . (int)$summary['already_paid']); ?></li>
                    <li><?php echo esc_html('expired_needs_review: ' . (int)$summary['expired_needs_review']); ?></li>
                    <li><?php echo esc_html('not_found: ' . (int)$summary['not_found']); ?></li>
                    <li><?php echo esc_html('no_token: ' . (int)$summary['no_token']); ?></li>
                    <li><?php echo esc_html('ambiguous_token: ' . (int)$summary['ambiguous_token']); ?></li>
                    <li><?php echo esc_html('duplicate_in_csv: ' . (int)$summary['duplicate_in_csv']); ?></li>
                    <li><?php echo esc_html('not_reserved: ' . (int)$summary['not_reserved']); ?></li>
                    <li><?php echo esc_html('error: ' . (int)$summary['error']); ?></li>
                </ul>
            <?php endif; ?>

            <?php if (is_array($results)): ?>
                <h2><?php echo esc_html__('Results', 'wp-simple-stock-checkout'); ?></h2>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Row', 'wp-simple-stock-checkout'); ?></th>
                            <th><?php echo esc_html__('Status', 'wp-simple-stock-checkout'); ?></th>
                            <th><?php echo esc_html__('Token', 'wp-simple-stock-checkout'); ?></th>
                            <th><?php echo esc_html__('Amount', 'wp-simple-stock-checkout'); ?></th>
                            <th><?php echo esc_html__('Message', 'wp-simple-stock-checkout'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><?php echo (int)$r['row']; ?></td>
                                <td><code><?php echo esc_html((string)$r['status']); ?></code></td>
                                <td><code><?php echo esc_html((string)($r['token'] ?? '')); ?></code></td>
                                <td><?php echo $r['amount'] !== null ? esc_html(number_format((float)$r['amount'], 2)) : ''; ?></td>
                                <td><?php echo esc_html((string)($r['message'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="description">
                    <?php echo esc_html__('Nota: les comandes expirades no es marquen com a pagades automàticament (requereixen revisió).', 'wp-simple-stock-checkout'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function handle_upload_and_reconcile(): array
    {
        if (!isset($_FILES['wpssc_csv']) || !is_array($_FILES['wpssc_csv'])) {
            throw new \RuntimeException('Missing CSV upload.');
        }

        $f = $_FILES['wpssc_csv'];

        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed.');
        }

        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Invalid upload.');
        }

        // mida màxima (5MB)
        $maxSize = 5 * 1024 * 1024;
        $size = (int)($f['size'] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            throw new \RuntimeException('CSV size exceeds limit (5MB).');
        }

        $parser = new CsvPaymentParser();
        $parsed = $parser->parse_file($tmp, 10000);

        $service = new PaymentReconciliationService();
        return $service->reconcile($parsed);
    }

    private function summarize(array $results): array
    {
        $keys = [
            'ok_paid', 'already_paid', 'expired_needs_review', 'not_found',
            'no_token', 'ambiguous_token', 'duplicate_in_csv', 'not_reserved', 'error',
        ];

        $sum = array_fill_keys($keys, 0);

        foreach ($results as $r) {
            $s = (string)($r['status'] ?? '');
            if (!isset($sum[$s])) {
                // ignora status desconeguts
                continue;
            }
            $sum[$s]++;
        }

        return $sum;
    }
}
