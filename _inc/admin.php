<?php

/**
 * @todo Convert this code into a class
 * @todo On edit.php page, need to filter only children posts of password protected parents (not all child posts)
 */

/**
 * Enqueue admin scripts and stylesheets
 *
 * @return void
 */

add_action('admin_enqueue_scripts', function () {

    wp_enqueue_style('ptc-admin–css', PTC_PLUGIN_URL . 'assets/css/admin.css');

    // load of gutenberg is disabled
    // if( !function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() )
    //     wp_enqueue_script('ptc-admin-js', PTC_PLUGIN_URL . 'assets/js/admin.js');

});

function myguten_enqueue() {

    // wp_enqueue_script('ptc-admin-js', PTC_PLUGIN_URL . 'assets/js/admin.js');

    // wp_enqueue_script(
    //     'myguten-script',
    //     plugins_url( 'myguten.js', __FILE__ )
    // );
}
add_action( 'enqueue_block_editor_assets', 'myguten_enqueue' );

/**
 * Handle new admin option to password protect child posts
 *
 * @return void
 */

add_action( 'save_post', 'ptc_save_post_meta', 10, 3 );
function ptc_save_post_meta($post_id, $post, $update) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;

    if (!current_user_can('edit_post', $post_id))
        return;


    if( isset($_POST['protect_children']) && $_POST['protect_children'] ) {
        $protect_children = "1";
    }
    else 
        $protect_children =  "";

    update_post_meta($post_id, 'protect_children', $protect_children);

};

/**
 * Add the option to protect child posts - for classic editor
 *
 * @return void
 */

add_action('post_submitbox_misc_actions', function ($post) {

    $post_type = $post->post_type;

    if (isPasswordProtected($post)) {
        $checked = get_post_meta($post->ID, 'protect_children', true) ? "checked" : "";
        echo "<div id=\"protect-children-div\"><input type=\"checkbox\" " . $checked . " name=\"protect_children\" /><strong>Password Protect</strong> all child posts</div>";
    }

});

/**
 * Register post meta field for Gutenberg post updates
 */
add_action('init', function () {

    register_post_meta( 'page', 'protect_children', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'boolean',
    ) );

});

/**
 * On admin page load of a child post, change the 'Visibility' for children post if
 * they are protected. There is no hook for that part of the admin section we have
 * to edit the outputted HTML.
 *
 * @param string $buffer The outputted HTML of the edit post page
 * @return string $buffer   Original or modified HTML
 */

add_action('admin_init', function () {
    // Abort on ajax requests
    if (wp_doing_ajax())
        return;

    global $pagenow;

    // On post list page
    if ('edit.php' === $pagenow) {

        ob_start(function ($buffer) {

            // @todo Not working yet below

            // Find children posts
            if (preg_match_all('/<tr id="post-(\d*?)".*? level-[12345].*?>/', $buffer, $matches)) {

                if(empty($matches[1]))
                    return $buffer;

                foreach($matches[1] as $child_post) {
                    $parent_post_ids = get_post_ancestors($child_post);

                    if ($post_id = protectTheChildrenEnabled($parent_post_ids)) {
                        $preg_pattern = sprintf('/(<\/strong>\n*<div.*?inline_%d">)/i', $child_post);
                        $buffer = preg_replace($preg_pattern, ' — <span class="post-state">Password protected by parent</span>$1', $buffer);
                    }
                }

            }

            return $buffer;

        });
    }

    // On single post edit page
    if ('post.php' === $pagenow && isset($_GET['post'])) {
        ob_start(function ($buffer) {

            $post = get_post($_GET['post']);

            // Check if it is a child post and if any parent/grandparent post has a password set
            $parent_ids = get_post_ancestors($post);

            if ($protected_parent = protectTheChildrenEnabled($parent_ids)) {

                // Change the wording to 'Password Protected' if the post is protected
                $buffer = preg_replace('/(<span id="post-visibility-display">)(.*)(<\/span>)/i', '$1Password protected$3', $buffer);

                // Remove Edit button post visibility (post needs to be updated from parent post)
                $buffer = preg_replace('/<a href="#visibility".*?><\/a>/i', '', $buffer);

                // Add 'Password protect by parent post' notice under visibility section
                $regex_pattern = '/(<\/div>)(\n*|.*)(<\!-- \.misc-pub-section -->)(\n*|.*)(<div class="misc-pub-section curtime misc-pub-curtime">)/i';
                $admin_edit_link = sprintf(admin_url('post.php?post=%d&action=edit'), $protected_parent);
                $update_pattern = sprintf('<br><span class="wp-media-buttons-icon password-protect-admin-notice">Password protected by <a href="%s">parent post</a></span>$1$2$3$4$5', $admin_edit_link);
                $buffer = preg_replace($regex_pattern, $update_pattern, $buffer);
            }

            return $buffer;
        });
    }


});


/**
 * Include Gutenberg specific script to add post editor checkbox in post status area.
 */

function enqueue_block_editor_assets() {
    wp_enqueue_script(
        'myguten-script',
        PTC_PLUGIN_URL . 'build/index.js',
        array( 'wp-blocks', 'wp-element', 'wp-components' )
    );
}
add_action( 'enqueue_block_editor_assets', 'enqueue_block_editor_assets' );