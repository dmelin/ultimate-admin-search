<?php
function ultimate_admin_search_page()
{
?>
    <div class="wrap">
        <h1><?php _e('Ultimate Admin Search', 'ultimate-admin-search'); ?></h1>
        <p><?php _e('Ultimate Admin Search will search any type of post for your content, even meta values. If you want to limit that search you can do so here by checking what it should include when searching.', 'ultimate-admin-search'); ?></p>

        <form>
            <input type="hidden" name="nonce" id="ultimate-admin-search-nonce" value="<?php echo wp_create_nonce('ultimate_admin_search_nonce'); ?>">
            <input type="hidden" name="action" id="action" value="ultimate_admin_search_save_settings">

            <input type="button" class="button button-primary ultimate-admin-search-save-settings" value="<?php _e('Save settings', 'ultimate-admin-search'); ?>">

            <div class="ultimate-admin-search-choices">
                <?php
                $post_types = ultimate_admin_search_get_all_post_types();
                $allowed_post_types = get_option('ultimate-admin-search-allowed-post-types', true);

                foreach ($post_types as $post_type) {
                ?>
                    <div>
                        <input type="checkbox" value="<?= $post_type->name; ?>" id="post-types-<?= $post_type->name; ?>" name="post-types[<?= $post_type->name; ?>]" <?php checked(isset($allowed_post_types[$post_type->name]) && $allowed_post_types[$post_type->name]); ?>>
                        <label for="post-types-<?= $post_type->name; ?>"><?= $post_type->labels->name; ?> <em><?= $post_type->name ?></em></label>
                    </div>
                <?php
                }
                ?>
            </div>
            <input type="button" class="button button-primary ultimate-admin-search-save-settings" value="<?php _e('Save settings', 'ultimate-admin-search'); ?>">

        </form>
    </div>
<?php
}

// Add content to admin footer
function ultimate_admin_search_footer()
{
?>
    <div id="ultimate-admin-search-modal" class="ultimate-admin-search-modal">
        <div id="ultimate-admin-search-modal__handle"></div>
        <div id="ultimate-admin-search-modal__search">
            <a href="<?= admin_url('admin.php?page=ultimate-admin-search') ?>" class="settings">
                <span class="dashicons dashicons-admin-generic"></span>
            </a>
            <input type=" text" id="ultimate-admin-search-input" placeholder="<?php _e('Type to search...', 'ultimate-admin-search'); ?>">
            <button id="ultimate-admin-search-button" class="button button-primary" type="button">
                <span class="dashicons dashicons-search"></span>
            </button>
        </div>
        <div id="ultimate-admin-search-modal__results"></div>
    </div>
<?php
}
add_action('admin_footer', 'ultimate_admin_search_footer');
