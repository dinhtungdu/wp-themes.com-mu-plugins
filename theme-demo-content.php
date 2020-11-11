<?php

function is_wordpress_org_theme_preview() {
	return true;
}

add_action('init', function () {
    $starter_content = wp_parse_args( get_theme_starter_content(), [
        'widgets' => [],
        'attachments' => [],
        'posts' => [],
        'options' => [],
        'nav_menus' => [],
        'theme_mods' => [],
    ] );

    // var_dump($starter_content['options']);
    // var_dump($starter_content['posts']);

    add_filter( 'pre_option_show_on_front', function () {
        return 'page';
    });
    add_filter( 'pre_option_page_on_front', function () {
        return 123;
    });
    add_filter( 'pre_option_page_for_posts', function () {
        return 456;
    });

    add_filter( 'redirect_canonical', '__return_false' );

    add_filter( 'posts_pre_query', function($posts, $query) use ( $starter_content ) {
        if(! $query->is_main_query()) {
            return $posts;
        }

        if( ! empty($query->query_vars['page_id']) && 123 == $query->query_vars['page_id']) {
            $post_data = $starter_content['posts']['front'] + [ 'ID' => 123, 'comment_status' => 'closed' ];
            return [ get_post( (object) $post_data ) ];
        }

        if( ! empty($query->query['name']) && 'about' == $query->query['name']) {
            $post_data = $starter_content['posts']['about'];
            $post_data += [
                'post_name' => 'about',
                'comment_status' => 'closed',
                'ID' => 789,
            ];
            return [ get_post( (object) $post_data ) ];
        }

        return $posts;
    }, 10, 2 );

    add_action('parse_request', function ($wp) {
        if ( isset($wp->query_vars['name']) && $wp->query_vars['name'] == 'blog') {
            $wp->query_vars = [
                'pagename' => 'blog',
            ];
            $wp->matched_rule = "(.?.+?)(?:/([0-9]+))?/?$";
            $wp->matched_query = "pagename=blog&page=";
        }
        // var_dump('hello');
    });

    add_action('parse_query', function ($query) use ( $starter_content ) {

        if (!$query->is_main_query()) {
            return;
        }

        if ('blog' == $query->query_vars['pagename']) {
            $post_data = $starter_content['posts']['blog'];
            $post_data += [
                'post_name' => 'blog',
                'comment_status' => 'closed',
                'ID' => 456,
            ];

            $query->queried_object = get_post((object) $post_data);
            $query->queried_object_id = 456;
            $query->is_page       = false;
            $query->is_singular   = false;
            $query->is_home       = true;
            $query->is_posts_page = true;
			$query->is_comment_feed = false;
        }
    }, 9999);

} );

add_filter( 'pre_get_shortlink', function() {
    return '';
}, 9999 );

add_action( 'wp_footer', function() {
    global $wp_query;
    if( $wp_query->is_main_query()) {
        var_dump( $wp_query );
    }
    var_dump( get_page_template() );
});