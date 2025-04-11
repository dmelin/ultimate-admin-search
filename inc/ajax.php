<?php

/**
 * Ultimate Admin Search
 * 
 * Handles AJAX requests for the Ultimate Admin Search plugin.
 * 
 * @package UltimateAdminSearch
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle all AJAX functionality for Ultimate Admin Search
 */
class Ultimate_Admin_Search_Ajax
{

    /**
     * Initialize hooks
     */
    public static function init()
    {
        // Admin AJAX endpoints
        add_action('wp_ajax_ultimate_admin_search_get_types', [self::class, 'get_post_types']);
        add_action('wp_ajax_ultimate_admin_search_get_posts', [self::class, 'get_posts']);
        add_action('wp_ajax_ultimate_admin_search_save_settings', [self::class, 'save_settings']);

        // Non-privileged users shouldn't access these endpoints, but in case hooks exist:
        add_action('wp_ajax_nopriv_ultimate_admin_search_get_types', [self::class, 'reject_unauthorized_access']);
        add_action('wp_ajax_nopriv_ultimate_admin_search_get_posts', [self::class, 'reject_unauthorized_access']);
        add_action('wp_ajax_nopriv_ultimate_admin_search_save_settings', [self::class, 'reject_unauthorized_access']);
    }

    /**
     * Handle unauthorized access
     */
    public static function reject_unauthorized_access()
    {
        wp_send_json_error('Unauthorized access', 403);
        exit;
    }

    /**
     * Get available post types for searching
     */
    public static function get_post_types()
    {
        // Verify nonce
        if (!self::verify_nonce('ultimate_admin_search_nonce')) {
            return;
        }

        $results = [];
        $allowed_post_types = self::get_allowed_post_types();
        $post_types = self::get_all_post_types();

        foreach ($post_types as $post_type) {
            // Skip if this post type is not allowed
            if (!self::is_post_type_allowed($post_type->name, $allowed_post_types)) {
                continue;
            }

            $results[] = [
                'id'    => $post_type->name,
                'title' => $post_type->labels->name,
                'icon'  => $post_type->menu_icon,
            ];
        }

        wp_send_json_success($results);
    }

    /**
     * Get posts for search results
     */
    public static function get_posts()
    {
        // Verify nonce
        if (!self::verify_nonce('ultimate_admin_search_nonce')) {
            return;
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $search_term = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        // Validate the post type
        if (empty($post_type) || !post_type_exists($post_type)) {
            wp_send_json_error('Invalid post type');
            return;
        }

        // Get posts from cache or database
        $posts = self::get_cached_posts($post_type);
        $results = [];

        // Skip searching if search term is empty
        if (empty($search_term)) {
            wp_send_json_success($results);
            return;
        }

        // Process each post for matches
        foreach ($posts as $post) {
            $matches = self::find_matches_in_post($post, $search_term);

            // If there are matches, add this post to results
            if (!empty($matches)) {
                $results[] = [
                    'id'      => $post->ID,
                    'title'   => get_the_title($post->ID),
                    'url'     => get_edit_post_link($post->ID),
                    'matched' => $matches,
                ];
            }
        }

        wp_send_json_success($results);
    }

    /**
     * Save plugin settings
     */
    public static function save_settings()
    {
        // Verify nonce
        if (!self::verify_nonce('ultimate_admin_search_nonce')) {
            return;
        }

        // Validate and sanitize post types
        $allowed_post_types = [];

        if (isset($_POST['post-types']) && is_array($_POST['post-types'])) {
            foreach ($_POST['post-types'] as $post_type => $enabled) {
                $post_type = sanitize_key($post_type);
                if (post_type_exists($post_type)) {
                    $allowed_post_types[$post_type] = (bool) $enabled;
                }
            }
        }

        // Clear post cache when settings change
        self::clear_post_caches();

        // Update option
        update_option('ultimate-admin-search-allowed-post-types', $allowed_post_types);

        wp_send_json_success($allowed_post_types);
    }

    /**
     * Get all registered post types
     * 
     * @return array Array of post type objects
     */
    private static function get_all_post_types()
    {
        return get_post_types([], 'objects');
    }

    /**
     * Get allowed post types from options
     * 
     * @return array Allowed post types
     */
    private static function get_allowed_post_types()
    {
        return get_option('ultimate-admin-search-allowed-post-types', []);
    }

    /**
     * Check if a post type is allowed
     * 
     * @param string $post_type_name The post type name
     * @param array $allowed_post_types Array of allowed post types
     * @return bool Whether post type is allowed
     */
    private static function is_post_type_allowed($post_type_name, $allowed_post_types)
    {
        // If no post types specifically enabled, all are allowed
        if (empty($allowed_post_types)) {
            return true;
        }

        return isset($allowed_post_types[$post_type_name]) && $allowed_post_types[$post_type_name];
    }

    /**
     * Get posts from cache or fetch from database
     * 
     * @param string $post_type Post type to fetch
     * @return array Array of post objects
     */
    private static function get_cached_posts($post_type)
    {
        $cache_key = 'ultimate_admin_search_posts_cache_' . sanitize_key($post_type);
        $posts = get_transient($cache_key);

        if (false === $posts) {
            $args = [
                'post_type'      => $post_type,
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];

            $posts = get_posts($args);

            // Cache the posts for 12 hours
            set_transient($cache_key, $posts, 12 * HOUR_IN_SECONDS);
        }

        return $posts;
    }

    /**
     * Clear all post caches
     */
    private static function clear_post_caches()
    {
        global $wpdb;

        $cache_keys = $wpdb->get_col(
            "SELECT `option_name` 
            FROM {$wpdb->options} 
            WHERE `option_name` LIKE '_transient_ultimate_admin_search_posts_cache_%'"
        );

        foreach ($cache_keys as $key) {
            $transient_name = str_replace('_transient_', '', $key);
            delete_transient($transient_name);
        }
    }

    /**
     * Find search term matches in a post
     * 
     * @param WP_Post $post The post object
     * @param string $search_term The search term
     * @return array Array of matches
     */
    private static function find_matches_in_post($post, $search_term)
    {
        $matches = [];

        // Search in post fields
        $search_areas = [
            'title'   => strip_tags($post->post_title),
            'content' => strip_tags($post->post_content),
            'excerpt' => strip_tags($post->post_excerpt),
            'slug'    => strip_tags($post->post_name),
        ];

        foreach ($search_areas as $key => $value) {
            $match = self::find_match_in_text($value, $search_term);
            if ($match) {
                $matches[] = [
                    'type'  => $key,
                    'value' => $match,
                ];
            }
        }

        // Search in post meta
        $meta_match = self::find_matches_in_post_meta($post->ID, $search_term);
        if ($meta_match) {
            $matches[] = $meta_match;
        }

        return $matches;
    }

    /**
     * Find a search term in text and return context
     * 
     * @param string $text The text to search in
     * @param string $search_term The search term
     * @return string|false The match with context or false if not found
     */
    private static function find_match_in_text($text, $search_term)
    {
        $str_pos = stripos($text, $search_term);

        if ($str_pos !== false) {
            $start = max(0, $str_pos - 20);
            $end = min(strlen($text), $str_pos + strlen($search_term) + 20);
            $snippet = substr($text, $start, $end - $start);

            // Extract the matched string from the snippet
            $matched_string = substr($text, $str_pos, strlen($search_term));

            // Encapsulate the matched string in <strong> tags
            $highlighted_snippet = str_ireplace($matched_string, "<strong>$matched_string</strong>", $snippet);

            return $highlighted_snippet;
        }

        return false;
    }

    /**
     * Find matches in post metadata
     * 
     * @param int $post_id The post ID
     * @param string $search_term The search term
     * @return array|false Match data or false if not found
     */
    private static function find_matches_in_post_meta($post_id, $search_term)
    {
        $post_meta = get_post_meta($post_id);

        foreach ($post_meta as $meta_key => $meta_values) {
            if (is_array($meta_values)) {
                foreach ($meta_values as $value) {
                    if (is_serialized($value)) {
                        continue; // Skip serialized data
                    }

                    $match = self::find_match_in_text($value, $search_term);
                    if ($match) {
                        return [
                            'type'  => 'meta',
                            'key'   => $meta_key,
                            'value' => $match,
                        ];
                    }
                }
            }
        }

        return false;
    }

    /**
     * Verify nonce for AJAX requests
     * 
     * @param string $nonce_name Name of the nonce
     * @return bool Whether nonce is valid
     */
    private static function verify_nonce($nonce_name)
    {
        if (!check_ajax_referer($nonce_name, 'ultimate-admin-search-nonce', false)) {
            wp_send_json_error('Invalid nonce mate', 403);
            return false;
        }
        return true;
    }
}

// Initialize hooks
Ultimate_Admin_Search_Ajax::init();
