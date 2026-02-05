<?php
namespace WPSSC\Admin;

use WPSSC\Capabilities;
use WPSSC\Settings;

if (!defined('ABSPATH')) { exit; }

final class AdminMenu {

    public function init(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_wpssc_save_settings', [$this, 'save_settings']);
    }

    public function register_menu(): void {
        add_menu_page(
            'WP Simple Stock Checkout',
            'Stock Checkout',
            Capabilities::CAP_MANAGE,
            'wpssc',
            [$this, 'render_settings'],
            'dashicons-cart',
            56
        );
    }

    public function render_settings(): void {
        if (!current_user_can(Capabilities::CAP_MANAGE)) wp_die('Not authorized');

        $s = Settings::all();
        ?>
        <div class="wrap">
            <h1>WP Simple Stock Checkout — Settings</h1>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpssc_save_settings'); ?>
                <input type="hidden" name="action" value="wpssc_save_settings">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Reserve minutes</th>
                        <td>
                            <input type="number" min="1" max="60" name="reserve_minutes"
                                   value="<?php echo esc_attr((int)($s['reserve_minutes'] ?? 15)); ?>">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Checkout URL (Child)</th>
                        <td>
                            <input type="url" class="regular-text" name="checkout_url_child"
                                   value="<?php echo esc_attr($s['checkout_url_child'] ?? ''); ?>">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Checkout URL (Adult)</th>
                        <td>
                            <input type="url" class="regular-text" name="checkout_url_adult"
                                   value="<?php echo esc_attr($s['checkout_url_adult'] ?? ''); ?>">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Require email</th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_email" value="1"
                                    <?php checked((int)($s['require_email'] ?? 1), 1); ?>>
                                Yes
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save'); ?>
            </form>
        </div>
        <?php
    }

    public function save_settings(): void {
        if (!current_user_can(Capabilities::CAP_MANAGE)) wp_die('Not authorized');
        check_admin_referer('wpssc_save_settings');

        $new = Settings::all();
        $new['reserve_minutes'] = max(1, min(60, (int)($_POST['reserve_minutes'] ?? 15)));
        $new['checkout_url_child'] = esc_url_raw($_POST['checkout_url_child'] ?? '');
        $new['checkout_url_adult'] = esc_url_raw($_POST['checkout_url_adult'] ?? '');
        $new['require_email'] = isset($_POST['require_email']) ? 1 : 0;

        Settings::update($new);

        wp_safe_redirect(admin_url('admin.php?page=wpssc&updated=1'));
        exit;
    }
}
