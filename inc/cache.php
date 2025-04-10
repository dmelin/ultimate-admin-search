<?php
// Clear post search cache when a post is saved
function ultimate_admin_search_clear_cache($post_id)
{
    $post_type = get_post_type($post_id);
    delete_transient('ultimate_admin_search_posts_cache_' . $post_type);
}
add_action('save_post', 'ultimate_admin_search_clear_cache');
// Clear post search cache when a post is deleted
function ultimate_admin_search_clear_cache_on_delete($post_id)
{
    $post_type = get_post_type($post_id);
    delete_transient('ultimate_admin_search_posts_cache_' . $post_type);
}
add_action('before_delete_post', 'ultimate_admin_search_clear_cache_on_delete');
// Clear post search cache when a post is trashed
function ultimate_admin_search_clear_cache_on_trash($post_id)
{
    $post_type = get_post_type($post_id);
    delete_transient('ultimate_admin_search_posts_cache_' . $post_type);
}
add_action('wp_trash_post', 'ultimate_admin_search_clear_cache_on_trash');
