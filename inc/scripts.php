<?php

/**
 * Enqueue the admin scripts and styles.
 */
function ultimate_admin_search_enqueue_scripts($hook)
{
    // Enqueue the script
    wp_enqueue_script(
        'ultimate-admin-search',
        plugin_dir_url(__FILE__) . '../js/ultimate-admin-search.js',
        array('jquery'),
        time(),
        true
    );

    // Enqueue the styles
    wp_enqueue_style(
        'ultimate-admin-search',
        plugin_dir_url(__FILE__) . '../css/ultimate-admin-search.css',
        array(),
        time()
    );
    // Localize the script with new data
    wp_localize_script('ultimate-admin-search', 'ultimateAdminSearch', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ultimate_admin_search_nonce'),
    ));
}
add_action('admin_enqueue_scripts', 'ultimate_admin_search_enqueue_scripts');