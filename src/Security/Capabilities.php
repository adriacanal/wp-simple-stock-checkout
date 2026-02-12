<?php
namespace WPSSC\Security;

if (!defined('ABSPATH')) { exit; }

final class Capabilities {
    public const CAP_MANAGE = 'manage_wpssc';

    public static function add_caps(): void {
        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::CAP_MANAGE)) {
            $role->add_cap(self::CAP_MANAGE);
        }
    }
}
