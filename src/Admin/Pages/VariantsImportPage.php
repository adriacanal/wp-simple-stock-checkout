<?php
namespace WPSSC\Admin\Pages;

use WPSSC\Security\Capabilities;
use WPSSC\Repositories\VariantRepository;

if (!defined('ABSPATH')) { exit; }

final class VariantsImportPage {

    public const PAGE_SLUG = 'wpssc-variants-import';

    public function init(): void {
        add_action('admin_post_wpssc_import_variants_csv', [$this, 'handle_import']);
    }

    public function render(): void {
        if (!current_user_can(Capabilities::CAP_MANAGE)) {
            wp_die(__('Not authorized', 'wp-simple-stock-checkout'));
        }

        $imported = isset($_GET['imported']) ? (int)$_GET['imported'] : 0;

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Import Variants (CSV)', 'wp-simple-stock-checkout'); ?></h1>

            <?php if ($imported > 0): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(sprintf(__('%d variants imported.', 'wp-simple-stock-checkout'), $imported)); ?></p>
                </div>
            <?php elseif (isset($_GET['imported']) && $imported === 0): ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php echo esc_html__('No variants were imported.', 'wp-simple-stock-checkout'); ?></p>
                </div>
            <?php endif; ?>

            <p><?php echo esc_html__('Expected header:', 'wp-simple-stock-checkout'); ?></p>
            <pre>sku,model,color,size,price,stock_total,is_active</pre>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('wpssc_import_variants_csv'); ?>
                <input type="hidden" name="action" value="wpssc_import_variants_csv">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('CSV file', 'wp-simple-stock-checkout'); ?></th>
                        <td><input type="file" name="csv_file" accept=".csv,text/csv" required></td>
                    </tr>
                </table>

                <?php submit_button(__('Import', 'wp-simple-stock-checkout')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_import(): void {
        if (!current_user_can(Capabilities::CAP_MANAGE)) wp_die(__('Not authorized', 'wp-simple-stock-checkout'));
        check_admin_referer('wpssc_import_variants_csv');

        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('Upload failed.', 'wp-simple-stock-checkout'));
        }

        $tmp = $_FILES['csv_file']['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) wp_die(__('Cannot read uploaded file.', 'wp-simple-stock-checkout'));

        $header = fgetcsv($fh, 0, ',', '"', '\\');
        if (!$header) wp_die(__('Empty CSV.', 'wp-simple-stock-checkout'));

        $header = array_map('trim', $header);
        $expected = ['sku','model','color','size','price','stock_total','is_active'];

        if ($header !== $expected) {
            wp_die(sprintf(__('Invalid header. Expected: %s', 'wp-simple-stock-checkout'), implode(',', $expected)));
        }

        $repo = new VariantRepository();

        $count = 0;
        $line = 1;

        while (($cols = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            $line++;

            // Skip blank lines
            if (count($cols) === 1 && trim($cols[0]) === '') continue;

            if (count($cols) !== count($expected)) {
                wp_die(sprintf(__('Invalid column count on line %d.', 'wp-simple-stock-checkout'), $line));
            }

            $row = array_combine($expected, array_map('trim', $cols));

            // Validation
            if ($row['sku'] === '') wp_die(sprintf(__('Missing sku on line %d.', 'wp-simple-stock-checkout'), $line));
            if ($row['price'] === '' || !is_numeric($row['price'])) wp_die(sprintf(__('Invalid price on line %d.', 'wp-simple-stock-checkout'), $line));
            if ($row['stock_total'] === '' || !is_numeric($row['stock_total'])) wp_die(sprintf(__('Invalid stock_total on line %d.', 'wp-simple-stock-checkout'), $line));
            if ($row['is_active'] === '' || !in_array($row['is_active'], ['0','1'], true)) wp_die(sprintf(__('Invalid is_active on line %d.', 'wp-simple-stock-checkout'), $line));

            // Normalize types
            $row['price'] = (float)$row['price'];
            $row['stock_total'] = (int)$row['stock_total'];
            $row['is_active'] = (int)$row['is_active'];

            $repo->upsert_by_sku($row);
            $count++;
        }

        fclose($fh);

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&imported=' . $count));
        exit;
    }
}
