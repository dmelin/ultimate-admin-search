<?php

/**
 * Ultimate Admin Search - Cache Management
 * 
 * Handles cache clearing for the Ultimate Admin Search plugin.
 * 
 * @package UltimateAdminSearch
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle cache management for Ultimate Admin Search
 */
class Ultimate_Admin_Search_Cache
{

    /**
     * Initialize hooks for cache management
     */
    public static function init()
    {
        // Clear cache when posts are modified
        add_action('save_post', [self::class, 'clear_post_type_cache']);
        add_action('before_delete_post', [self::class, 'clear_post_type_cache']);
        add_action('wp_trash_post', [self::class, 'clear_post_type_cache']);
        add_action('untrash_post', [self::class, 'clear_post_type_cache']);

        // Clear cache when post status changes
        add_action('transition_post_status', [self::class, 'clear_cache_on_status_change'], 10, 3);
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
     * Clear the cache for a specific post type
     *
     * @param int $post_id The ID of the affected post
     * @return bool Whether the cache was cleared
     */
    public static function clear_post_type_cache($post_id)
    {
        // Skip auto-saves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        // Skip post revisions
        if (wp_is_post_revision($post_id)) {
            return false;
        }

        $post_type = get_post_type($post_id);
        if (!$post_type) {
            return false;
        }

        $cache_key = 'ultimate_admin_search_posts_cache_' . sanitize_key($post_type);
        return delete_transient($cache_key);
    }

    /**
     * Clear cache when post status changes
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     * @return bool Whether the cache was cleared
     */
    public static function clear_cache_on_status_change($new_status, $old_status, $post)
    {
        if ($new_status === $old_status) {
            return false;
        }

        return self::clear_post_type_cache($post->ID);
    }

    /**
     * Clear all post type caches
     * 
     * @return int Number of caches cleared
     */
    public static function clear_all_caches()
    {
        global $wpdb;

        $count = 0;
        $cache_keys = $wpdb->get_col(
            "SELECT `option_name` 
            FROM {$wpdb->options} 
            WHERE `option_name` LIKE '_transient_ultimate_admin_search_posts_cache_%'"
        );

        foreach ($cache_keys as $key) {
            $transient_name = str_replace('_transient_', '', $key);
            if (delete_transient($transient_name)) {
                $count++;
            }
        }

        return $count;
    }
}

// Initialize cache management hooks
Ultimate_Admin_Search_Cache::init();
