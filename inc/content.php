<?php

/**
 * Ultimate Admin Search - Frontend Components
 * 
 * Handles UI rendering for the Ultimate Admin Search plugin.
 * 
 * @package UltimateAdminSearch
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle UI components for Ultimate Admin Search
 */
class Ultimate_Admin_Search_UI
{

    /**
     * Initialize hooks for UI components
     */
    public static function init()
    {
        // Add admin page
        add_action('admin_menu', [self::class, 'register_admin_page']);

        // Add search modal to admin footer
        add_action('admin_footer', [self::class, 'render_search_modal']);

        // Enqueue assets
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Register the admin settings page
     */
    public static function register_admin_page()
    {
        add_menu_page(
            __('Ultimate Admin Search', 'ultimate-admin-search'),
            __('Admin Search', 'ultimate-admin-search'),
            'manage_options',
            'ultimate-admin-search',
            [self::class, 'render_settings_page'],
            'dashicons-search',
            0
        );
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public static function enqueue_assets()
    {
        // Enqueue the script
        wp_enqueue_script(
            'ultimate-admin-search',
            plugin_dir_url(dirname(__FILE__)) . 'js/ultimate-admin-search.js',
            array('jquery'),
            ULTIMATE_ADMIN_SEARCH_VERSION,
            true
        );

        // Enqueue the styles
        wp_enqueue_style(
            'ultimate-admin-search',
            plugin_dir_url(dirname(__FILE__)) . 'css/ultimate-admin-search.css',
            array(),
            ULTIMATE_ADMIN_SEARCH_VERSION
        );

        // Localize the script with new data
        wp_localize_script('ultimate-admin-search', 'ultimateAdminSearch', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ultimate_admin_search_nonce'),
        ));
    }

    /**
     * Render the settings page
     */
    public static function render_settings_page()
    {
        // Get post types and settings
        $post_types = self::get_all_post_types();
        $allowed_post_types = get_option('ultimate-admin-search-allowed-post-types', []);

?>
        <div class="wrap ultimate-admin-search-settings">
            <h1><?php _e('Ultimate Admin Search', 'ultimate-admin-search'); ?></h1>

            <p class="description">
                <?php _e('Ultimate Admin Search will search any type of post for your content, even meta values. If you want to limit that search you can do so here by checking what it should include when searching.', 'ultimate-admin-search'); ?>
            </p>

            <form id="ultimate-admin-search-settings-form">
                <?php wp_nonce_field('ultimate_admin_search_nonce', 'ultimate-admin-search-nonce'); ?>
                <input type="hidden" name="action" value="ultimate_admin_search_save_settings">

                <h2><?php _e('Searchable Post Types', 'ultimate-admin-search'); ?></h2>

                <div class="ultimate-admin-search-choices">

                    <?php foreach ($post_types as $post_type) :
                        $is_checked = isset($allowed_post_types[$post_type->name]) && $allowed_post_types[$post_type->name];
                        $input_id = 'post-types-' . esc_attr($post_type->name);
                    ?>
                        <div class="post-type-option">
                            <input type="checkbox"
                                value="1"
                                id="<?php echo $input_id; ?>"
                                name="post-types[<?php echo esc_attr($post_type->name); ?>]"
                                <?php checked($is_checked); ?>>
                            <label for="<?php echo $input_id; ?>">
                                <?php echo esc_html($post_type->labels->name); ?>
                                <em>(<?php echo esc_html($post_type->name); ?>)</em>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="ultimate-admin-search-actions">
                    <button type="submit" class="button button-primary ultimate-admin-search-save-settings">
                        <?php _e('Save Settings', 'ultimate-admin-search'); ?>
                    </button>
                </div>
            </form>

            <div class="ultimate-admin-search-notices" style="display: none;"></div>
        </div>
    <?php
    }

    /**
     * Render the search modal in admin footer
     */
    public static function render_search_modal()
    {
    ?>
        <div id="ultimate-admin-search-modal" class="ultimate-admin-search-modal">
            <button type="button" id="ultimate-admin-search-modal__handle" class="primary button-primary">
            </button>

            <div id="ultimate-admin-search-modal__search">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ultimate-admin-search')); ?>"
                    class="settings" title="<?php esc_attr_e('Settings', 'ultimate-admin-search'); ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                </a>

                <input type="text"
                    id="ultimate-admin-search-input"
                    placeholder="<?php esc_attr_e('Type to search...', 'ultimate-admin-search'); ?>"
                    aria-label="<?php esc_attr_e('Search content', 'ultimate-admin-search'); ?>">

                <button id="ultimate-admin-search-button"
                    class="button button-primary"
                    type="button">
                    <span class="dashicons dashicons-search"></span>
                </button>
            </div>

            <div id="ultimate-admin-search-modal__results"></div>

            <?php wp_nonce_field('ultimate_admin_search_nonce', 'ultimate-admin-search-modal-nonce'); ?>
        </div>
<?php
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
}

// Initialize UI components
Ultimate_Admin_Search_UI::init();
