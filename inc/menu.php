<?php

// Add the admin menu item
function ultimate_admin_search_menu()
{
    add_menu_page(
        __('Ultimate Admin Search', 'ultimate-admin-search'),
        __('Admin Search', 'ultimate-admin-search'),
        'manage_options',
        'ultimate-admin-search',
        'ultimate_admin_search_page',
        'dashicons-search',
        0
    );
}
add_action('admin_menu', 'ultimate_admin_search_menu');
