<?php
function ultimate_admin_search_get_all_post_types()
{
    $post_types = get_post_types(array(), 'objects');

    // Sort the post types by their labels
    usort($post_types, function ($a, $b) {
        return strcmp($a->labels->name, $b->labels->name);
    });

    return $post_types;
}
