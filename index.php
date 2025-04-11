<?php

/**
 * Plugin Name: Ultimate Admin Search
 * Plugin URI: https://dmelin.se
 * Description: Search anything in the WordPress admin area.
 * Version: 1.0.0
 * Author: Daniel Melin
 * Author URI: https://dmelin.se
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ultimate-admin-search
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('ULTIMATE_ADMIN_SEARCH_VERSION', '1.0.0');

require_once __DIR__ . '/inc/ajax.php';

require_once __DIR__ . '/inc/content.php';

require_once __DIR__ . '/inc/cache.php';
