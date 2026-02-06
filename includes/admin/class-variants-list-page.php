<?php
namespace WPSSC\Admin;

use WPSSC\Capabilities;
use WPSSC\Repositories\VariantRepository;

if (!defined('ABSPATH')) { exit; }

final class VariantsListPage {

    public const PAGE_SLUG = 'wpssc-variants';

    public function render(): void {
        if (!current_user_can(Capabilities::CAP_MANAGE)) {
            wp_die('Not authorized');
        }

        $table = new VariantsListTable();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1>Variants</h1>
            <?php $table->display(); ?>
        </div>
        <?php
    }
}
