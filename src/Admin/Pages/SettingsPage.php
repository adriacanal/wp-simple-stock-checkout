<?php

namespace WPSSC\Admin\Pages;

use WPSSC\Security\Capabilities;
use WPSSC\Settings\Settings;

if (!defined('ABSPATH')) { exit; }

final class SettingsPage
{
    public const PAGE_SLUG = 'wpssc-settings';

    public function render(): void
    {
        if (!current_user_can(Capabilities::CAP_MANAGE)) {
            wp_die(__('No tens permisos per accedir aquí.', 'wp-simple-stock-checkout'));
        }

        $all = Settings::all();

        $reserve_minutes = (int) ($all['reserve_minutes'] ?? 15);
        $reserve_minutes = max(1, min(1440, $reserve_minutes));

        $checkout_child = (string) ($all['checkout_url_child'] ?? '');
        $checkout_adult = (string) ($all['checkout_url_adult'] ?? '');
        $require_email  = (int) ($all['require_email'] ?? 1);

        $action_url = admin_url('admin-post.php');
        $updated = isset($_GET['settings-updated']) && (string) $_GET['settings-updated'] === '1';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Settings', 'wp-simple-stock-checkout'); ?></h1>

            <?php if ($updated): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html__('Configuració desada correctament.', 'wp-simple-stock-checkout'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url($action_url); ?>">
                <input type="hidden" name="action" value="wpssc_save_settings" />
                <?php wp_nonce_field('wpssc_save_settings', '_wpssc_nonce'); ?>

                <h2 class="title"><?php echo esc_html__('Reserves', 'wp-simple-stock-checkout'); ?></h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="wpssc_reserve_minutes"><?php echo esc_html__('Temps de reserva (minuts)', 'wp-simple-stock-checkout'); ?></label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="wpssc_reserve_minutes"
                                name="wpssc_settings[reserve_minutes]"
                                value="<?php echo esc_attr((string) $reserve_minutes); ?>"
                                class="small-text"
                                min="1"
                                max="1440"
                                step="1"
                            />
                            <p class="description">
                                <?php echo esc_html__('Temps que l’estoc queda bloquejat mentre la família completa el pagament. Recomanat: 10–20 minuts.', 'wp-simple-stock-checkout'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php echo esc_html__('Email obligatori', 'wp-simple-stock-checkout'); ?></th>
                        <td>
                            <input type="hidden" name="wpssc_settings[require_email]" value="0" />
                            <label>
                                <input
                                    type="checkbox"
                                    name="wpssc_settings[require_email]"
                                    value="1"
                                    <?php checked($require_email, 1); ?>
                                />
                                <?php echo esc_html__('Demana email per fer una reserva.', 'wp-simple-stock-checkout'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php echo esc_html__('Checkout extern', 'wp-simple-stock-checkout'); ?></h2>
                <p class="description">
                    <?php echo esc_html__('URL del sistema de pagament extern. El plugin hi afegirà el token com a paràmetre (?token=...).', 'wp-simple-stock-checkout'); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="wpssc_checkout_child"><?php echo esc_html__('Checkout URL (infantil)', 'wp-simple-stock-checkout'); ?></label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="wpssc_checkout_child"
                                name="wpssc_settings[checkout_url_child]"
                                value="<?php echo esc_attr($checkout_child); ?>"
                                class="regular-text ltr"
                                placeholder="https://..."
                                autocomplete="off"
                            />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wpssc_checkout_adult"><?php echo esc_html__('Checkout URL (adult)', 'wp-simple-stock-checkout'); ?></label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="wpssc_checkout_adult"
                                name="wpssc_settings[checkout_url_adult]"
                                value="<?php echo esc_attr($checkout_adult); ?>"
                                class="regular-text ltr"
                                placeholder="https://..."
                                autocomplete="off"
                            />
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Desar configuració', 'wp-simple-stock-checkout')); ?>
            </form>
        </div>
        <?php
    }
}
