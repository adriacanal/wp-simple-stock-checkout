<?php
namespace WPSSC\Admin\Pages;

use WPSSC\Security\Capabilities;
use WPSSC\Settings\Settings;

if (!defined('ABSPATH')) { exit; }

final class SettingsPage {

    public const PAGE_SLUG = 'wpssc-settings';

    public function init(): void {
        // Form handler
        add_action('admin_post_wpssc_save_settings', [$this, 'save']);
    }

    public function render(): void {
        if (!current_user_can(Capabilities::CAP_MANAGE)) {
            wp_die('Not authorized');
        }

        $s = Settings::all();
        $reserve_minutes   = (int)($s['reserve_minutes'] ?? 15);
        $checkout_child    = (string)($s['checkout_url_child'] ?? '');
        $checkout_adult    = (string)($s['checkout_url_adult'] ?? '');
        $require_email     = (int)($s['require_email'] ?? 1);

        ?>
        <div class="wrap">
            <h1>WP Simple Stock Checkout — Settings</h1>

            <?php if (!empty($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpssc_save_settings'); ?>
                <input type="hidden" name="action" value="wpssc_save_settings">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="reserve_minutes">Reserve minutes</label></th>
                        <td>
                            <input
                                id="reserve_minutes"
                                type="number"
                                min="1"
                                max="60"
                                name="reserve_minutes"
                                value="<?php echo esc_attr($reserve_minutes); ?>"
                            >
                            <p class="description">How long a stock reservation remains valid.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="checkout_url_child">Checkout URL (Child)</label></th>
                        <td>
                            <input
                                id="checkout_url_child"
                                type="url"
                                class="regular-text"
                                name="checkout_url_child"
                                value="<?php echo esc_attr($checkout_child); ?>"
                            >
                            <p class="description">External checkout URL for child products (optional).</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="checkout_url_adult">Checkout URL (Adult)</label></th>
                        <td>
                            <input
                                id="checkout_url_adult"
                                type="url"
                                class="regular-text"
                                name="checkout_url_adult"
                                value="<?php echo esc_attr($checkout_adult); ?>"
                            >
                            <p class="description">External checkout URL for adult products (optional).</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Require email</th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_email" value="1" <?php checked($require_email, 1); ?>>
                                Yes
                            </label>
                            <p class="description">If enabled, the checkout form will require an email.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save'); ?>
            </form>
        </div>
        <?php
    }

    public function save(): void {
        if (!current_user_can(Capabilities::CAP_MANAGE)) {
            wp_die('Not authorized');
        }

        check_admin_referer('wpssc_save_settings');

        $current = Settings::all();

        $reserve_minutes = isset($_POST['reserve_minutes']) ? (int)$_POST['reserve_minutes'] : (int)($current['reserve_minutes'] ?? 15);
        $reserve_minutes = max(1, min(60, $reserve_minutes));

        $checkout_url_child = isset($_POST['checkout_url_child']) ? esc_url_raw((string)$_POST['checkout_url_child']) : '';
        $checkout_url_adult = isset($_POST['checkout_url_adult']) ? esc_url_raw((string)$_POST['checkout_url_adult']) : '';

        $require_email = isset($_POST['require_email']) ? 1 : 0;

        $new = $current;
        $new['reserve_minutes'] = $reserve_minutes;
        $new['checkout_url_child'] = $checkout_url_child;
        $new['checkout_url_adult'] = $checkout_url_adult;
        $new['require_email'] = $require_email;

        Settings::update($new);

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&updated=1'));
        exit;
    }
}
