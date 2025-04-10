<?php

/**
 * Handle the AJAX request.
 */
function ultimate_admin_search_get_types()
{
    // Check the nonce
    check_ajax_referer('ultimate_admin_search_nonce', 'nonce');

    $results = array();

    $allowed_post_types = get_option('ultimate-admin-search-allowed-post-types', true);

    $post_types = ultimate_admin_search_get_all_post_types();
    foreach ($post_types as $post_type) {
        // Check if the post type is allowed
        if ((!isset($allowed_post_types[$post_type->name]) || !$allowed_post_types[$post_type->name]) && (is_array($allowed_post_types) && count($allowed_post_types) > 0)) {
            continue;
        }

        $results[] = array(
            'id' => $post_type->name,
            'title' => $post_type->labels->name,
            'icon' => $post_type->menu_icon,
        );
    }

    // Return the results
    wp_send_json_success($results);
}
add_action('wp_ajax_ultimate_admin_search_get_types', 'ultimate_admin_search_get_types');
add_action('wp_ajax_nopriv_ultimate_admin_get_types', 'ultimate_admin_search_get_types');

function ultimate_admin_search_get_posts()
{
    // Check the nonce
    check_ajax_referer('ultimate_admin_search_nonce', 'nonce');

    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
    $search_term = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    // Check if we have a search cache
    $posts = get_transient('ultimate_admin_search_posts_cache_' . $post_type);

    if (!$posts) {
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'any',
            // Order by date desc
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $posts = get_posts($args);

        // Cache the posts for 12 hours
        set_transient('ultimate_admin_search_posts_cache_' . $post_type, $posts, 12 * HOUR_IN_SECONDS);
    } else {
        error_log('Cache hit for post type: ' . $post_type);
    }

    $results = array();
    foreach ($posts as $post) {
        $matches = array();
        $search_areas = array(
            'title' => strip_tags($post->post_title),
            'content' => strip_tags($post->post_content),
            'excerpt' => strip_tags($post->post_excerpt),
            'slug' => strip_tags($post->post_name),
        );
        foreach ($search_areas as $key => $value) {
            $str_pos = stripos($value, $search_term);
            if ($str_pos !== false) {
                $start = max(0, $str_pos - 20);
                $end = min(strlen($value), $str_pos + strlen($search_term) + 20);
                $value = substr($value, $start, $end - $start);

                $matches[] = array(
                    'type' => $key,
                    'value' => $value,
                );
            }
        }

        $post_meta = get_post_meta($post->ID);
        $meta_matched = false;
        foreach ($post_meta as $meta_key => $meta_value) {
            if (is_array($meta_value)) {
                foreach ($meta_value as $value) {
                    $str_pos = stripos($value, $search_term);
                    if ($str_pos !== false) {
                        $start = max(0, $str_pos - 20);
                        $end = min(strlen($value), $str_pos + strlen($search_term) + 20);
                        $value = substr($value, $start, $end - $start);

                        $matches[] = array(
                            'type' => 'meta',
                            'key' => $meta_key,
                            'value' => $value,
                        );
                        $meta_matched = true;
                    }
                }
            } else {
                $str_pos = stripos($meta_value, $search_term);
                if ($str_pos !== false) {
                    $start = max(0, $str_pos - 20);
                    $end = min(strlen($meta_value), $str_pos + strlen($search_term) + 20);
                    $value = substr($meta_value, $start, $end - $start);

                    $matches[] = array(
                        'type' => 'meta',
                        'key' => $meta_key,
                        'value' => $value,
                    );
                    $meta_matched = true;
                }
            }

            if ($meta_matched) break;
        }

        if (count($matches)) $results[] = array(
            'id' => $post->ID,
            'title' => get_the_title($post->ID),
            'url' => get_edit_post_link($post->ID),
            'matched' => $matches,
        );
    }

    wp_send_json_success($results);
}
add_action('wp_ajax_ultimate_admin_search_get_posts', 'ultimate_admin_search_get_posts');
add_action('wp_ajax_nopriv_ultimate_admin_search_get_posts', 'ultimate_admin_search_get_posts');

function ultimate_admin_search_save_settings()
{
    // Check the nonce
    check_ajax_referer('ultimate_admin_search_nonce', 'nonce');

    $allowed_post_types = isset($_POST['post-types']) ? $_POST['post-types'] : array();

    // Save the allowed post types in the database
    update_option('ultimate-admin-search-allowed-post-types', $allowed_post_types);

    wp_send_json_success($allowed_post_types);
}
add_action('wp_ajax_ultimate_admin_search_save_settings', 'ultimate_admin_search_save_settings');
add_action('wp_ajax_nopriv_ultimate_admin_search_save_settings', 'ultimate_admin_search_save_settings');
