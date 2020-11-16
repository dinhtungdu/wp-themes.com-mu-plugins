<?php
define( 'DISABLE_WP_CRON', true);
function is_wordpress_org_theme_preview() {
	return true;
}

class WP_Themes_Theme_Preview {
    private $starter_content;

    private $mapping = [];

    public function init() {
        $this->set_starter_content();

        if( empty( $this->starter_content)) {
            return;
        }

        $this->set_options();
        $this->filter_posts();
        $this->filter_nav_menus();
        $this->filter_post_thumbnails();
        $this->filter_sidebars();

        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
        remove_action( 'template_redirect', 'redirect_canonical' );

        add_action( 'wp_footer', [ $this, 'debug' ] );
    }

    public function filter_posts() {
        add_filter( 'posts_pre_query', [ $this, 'filter_page_query'], 10, 2 );
        add_action('parse_request', [ $this, 'filter_blog_request' ] );
        add_filter( 'parse_query', [ $this, 'filter_blog_page_query'] );
    }

    public function filter_post_thumbnails() {

        add_filter( 'get_post_metadata', function( $value, $post_id, $meta_key ) {
            if(
                ! in_array( $post_id, array_values($this->mapping['posts']))
                || '_thumbnail_id' !== $meta_key
            ) {
                return $value;
            }

            $post_data = $this->find_data_by_id($post_id);
            if( empty($post_data['thumbnail'])) {
                return $value;
            }
            return $post_data['thumbnail'];
        }, 10, 3 );

        add_filter('wp_get_attachment_image_src', function( $image, $attachment_id, $size) {
            $image_data = $this->find_data_by_id( $attachment_id, 'attachments');
            if( empty( $image_data)) {
                return $image;
            }
            $image_url = sprintf(
                '%1$s/%2$s',
                untrailingslashit( get_template_directory_uri() ),
                ltrim($image_data['file'],'/')
            );

            $image_sizes = wp_get_registered_image_subsizes();
            $width = 0;
            $height = 0;
            if( !empty($image_sizes[$size])) {
                $width = $image_sizes[$size]['width'];
                $height = $image_sizes[$size]['height'];
            }

            return [ $image_url, $width, $height ];
        }, 10, 3);
    }

    public function filter_nav_menus() {
        if( empty($this->starter_content['nav_menus'])) {
            return;
        }

        add_filter( 'has_nav_menu', function( $has_nav_menu, $location ) {
            if( empty($this->starter_content['nav_menus'][$location])) {
                return $has_nav_menu;
            }
            return true;
        }, 10, 2);

        add_filter( 'theme_mod_nav_menu_locations', function() {
            return $this->mapping['nav_menus'];
        });

        add_filter( 'wp_get_nav_menu_object', function( $menu_objects, $menu ) {
            foreach( $this->mapping['nav_menus'] as $location => $menu_id ) {
                if( $menu_id != $menu ) {
                    continue;
                }
                $menu_objects = new WP_Term( (object) [
                    'taxonomy' => 'nav_menu',
                    'term_id' => $menu_id,
                    'slug' => $location,
                    'name' => $this->starter_content['nav_menus'][$location]['name'],
                    'term_taxonomy_id' => $menu_id,
                    'count' => count( $this->starter_content['nav_menus'][$location]['items']),
                ] );
            }

            return $menu_objects;
        }, 10, 2 );

        add_filter( 'wp_get_nav_menu_items', function ($items, $menu, $args) {
            foreach( $this->mapping['nav_menus'] as $location => $menu_id ) {
                if( $menu_id != $menu->term_id ) {
                    continue;
                }

                $menu_items = [];
                foreach( $this->starter_content['nav_menus'][$location]['items'] as $index => $item ) {
                    $item = wp_parse_args($item, [
                        'db_id'   => 0,
                        'object_id'   => 0,
                        'object'      => '',
                        'parent_id'   => 0,
                        'position'    => 0,
                        'type'        => 'custom',
                        'title'       => '',
                        'url'         => '',
                        'description' => '',
                        'attr-title'  => '',
                        'target'      => '',
                        'classes'     => '',
                        'xfn'         => '',
                        'status'      => '',
                        'menu_order' => $index, 
                        'menu_item_parent' => 0,
                        'post_parent' => 0,
                        'ID' => $this->generate_id(),
                    ]);

                    if( $item['type'] === 'custom') {
                        $item['object'] = 'custom';
                    }

                    if( ! empty( $item['title']) && !empty($item['url'])) {
                        $menu_items[] = (object) $item;
                        continue;
                    }

                    if( !empty($item['object_id']) && $item['type'] === 'post_type') {
                        foreach( $this->mapping['posts'] as $name => $id) {
                            if( $id !== $item['object_id']) {
                               continue;
                            }
                            $post_data = $this->find_data_by_id( $item['object_id']);
                            if(  empty($post_data)) {
                                continue;
                            }
                            $item['url'] = home_url($post_data['post_name']);
                            $item['title'] = $post_data['post_title'];

                            $menu_items[] = (object) $item;
                            continue;
                        }
                    }
                }
            }

            if( empty( $menu_items)) {
                return $items;
            }
            return $menu_items;
        }, 10, 3 );

    }

    public function filter_sidebars() {
        if( empty ( $this->starter_content['widgets'] ) ) {
            return;
        }

        $widgets = [];
        $widgets_options = [];

        $number = 1;
        foreach( $this->starter_content['widgets'] as $sidebar => $sidebar_widgets) {
            foreach( $sidebar_widgets as $widget ) {
                $widgets[] = [
                    'number' => $number,
                    'type' => $widget[0],
                    'sidebar' => $sidebar,
                    'settings' => $widget[1],
                ];
                $widgets_options[$widget[0]][$number] = $widget[1];
                $number++;
            }
        }

        foreach( $widgets_options as $type => $options ) {
            add_filter( "pre_option_widget_$type", function() use ( $options ) {
                return $options;
            });
        }

        foreach( $this->starter_content['widgets'] as $sidebar => $sidebar_widgets) {
            add_filter( 'is_active_sidebar', function( $is_active_sidebar, $index ) use ($sidebar) {
                    if( $index == $sidebar ) {
                            return true;
                    }
                    return $is_active_sidebar;
            }, 10, 2 );
        }
        add_filter( 'sidebars_widgets', function() use ( $widgets ) {
            $sidebars_widgets = [];
            foreach ( $widgets as $widget) {
                list('type' => $type, 'number' => $number) = $widget;
                $sidebars_widgets[$widget['sidebar']][] = "$type-$number";
            }
            return $sidebars_widgets;
        } );

        foreach ( $widgets as $widget) {
            list('type' => $type, 'number' => $number) = $widget;
            $widget_class = $this->get_widget_class_from_type( $type );
            if( ! $widget_class ) {
                continue;
            }
            wp_register_sidebar_widget(
                "$type-$number",
                $widget_class->id_base,
                [$widget_class, 'display_callback'],
                ['classname' => "widget_$type"],
                ['number' => $number]
            );
        }
    }

    private function get_widget_class_from_type( $type ) {
        global $wp_widget_factory;
        foreach ( $wp_widget_factory->widgets as $widget) {
            if( $widget->id_base === $type) {
                return $widget;
            }
        }
        return false;
    }

    public function set_starter_content() {
        $starter_content = get_theme_starter_content();

        if( ! empty( $starter_content['posts'])) {
            foreach( $starter_content['posts'] as $name => &$data) {
                $this->mapping['posts'][$name] = $this->generate_id( $name );
                $data['ID'] = $this->mapping['posts'][$name];
                $data['post_name'] = $name;
                if( $data['post_type'] == 'page') {
                    $data['comment_status'] = 'closed'; 
                }
            }
        }

        if( ! empty( $starter_content['attachments'])) {
            foreach( $starter_content['attachments'] as $name => &$data) {
                $this->mapping['attachments'][$name] = $this->generate_id();
                $data['ID'] = $this->mapping['attachments'][$name];
            }
        }

        if( ! empty( $starter_content['nav_menus'])) {
            foreach( $starter_content['nav_menus'] as $name => &$data ) {
                $nav_menu_id = $this->generate_id();
                $this->mapping['nav_menus'][$name] = $nav_menu_id;
                $data['ID'] = $this->mapping['nav_menus'][$name];
            }
        }

        array_walk_recursive( $starter_content, function( &$value) {
            if ( preg_match( '/^{{(?P<symbol>.+)}}$/', $value, $matches ) ) {
                if ( isset( $this->mapping['posts'][ $matches['symbol'] ] ) ) {
                    $value =  $this->mapping['posts'][ $matches['symbol'] ];
                }
                elseif ( isset( $this->mapping['attachments'][ $matches['symbol'] ] ) ) {
                    $value =  $this->mapping['attachments'][ $matches['symbol'] ];
                }
            }
        } );

        $this->starter_content = $starter_content;

    }

    public function set_options() {
        if( empty( $this->starter_content['options'])) {
            return;
        }
        foreach( $this->starter_content['options'] as $option => $value) {
            add_filter( "pre_option_$option", function() use( $value ) {
                return $value;
            });
        }
    }

    public function disable_shortlink() {
        return '';
    }

    public function filter_page_query($posts, $query) {
        if(! $query->is_main_query() ) {
            return $posts;
        }

        $front_page_id = $this->starter_content['options']['page_on_front'];
        
        if(
            ! empty($query->query_vars['page_id'])
            && $front_page_id == $query->query_vars['page_id']
        ) {
            return [ get_post( (object) $this->find_data_by_id( $front_page_id, 'posts' ) ) ];
        }

        if( ! empty($query->query['name']) && ! empty($this->starter_content['posts'][$query->query['name']]) ) {
            return [ get_post( (object) $this->starter_content['posts'][$query->query['name']] ) ];
        }

        return $posts;
    }

    public function filter_blog_request($wp) {
        $blog_post_name = $this->get_blog_post_name();
        if ( isset($wp->query_vars['name']) && $wp->query_vars['name'] == $blog_post_name) {
            $wp->query_vars = [
                'pagename' => $blog_post_name,
            ];
            $wp->matched_rule = "(.?.+?)(?:/([0-9]+))?/?$";
            $wp->matched_query = "pagename=$blog_post_name&page=";
        }
    }

    public function filter_blog_page_query ($query) {

        if (!$query->is_main_query() ) {
            return;
        }

        $blog_post_name = $this->get_blog_post_name();

        if( ! $blog_post_name || $blog_post_name !== $query->query_vars['pagename'] ) {
            return;
        }

        $post_data = $this->starter_content['posts'][$blog_post_name];

        $query->queried_object = get_post((object) $post_data);
        $query->queried_object_id = $post_data['ID'];
        $query->is_page       = false;
        $query->is_singular   = false;
        $query->is_home       = true;
        $query->is_posts_page = true;
        $query->is_comment_feed = false;
    }

    private function get_blog_post_name() {
        if ( empty($this->starter_content['options']['page_for_posts']) ) {
            return false;
        }

        $blog_page_id = $this->starter_content['options']['page_for_posts'];
        $post_data = $this->find_data_by_id( $blog_page_id, 'posts' );

        return $post_data['post_name'];
    }

    private function find_data_by_id($id, $type = 'posts') {
        foreach ($this->starter_content[$type] as $name => $data ) {
            if( $id === $data['ID'] ) {
                return $data;
            }
        }

        return [];
    }

    private function generate_id( $page_name = '' ) {
        if( $page_name && $page = get_page_by_path($page_name) ) {
            return $page->ID;
        }
        return wp_rand( 1000, 10000 );
    }

    public function debug() {
        global $wp_query;

        var_dump($this->starter_content);
        if( $wp_query->is_main_query()) {
            var_dump( $wp_query );
        }
        var_dump( get_page_template() );
    }

}

add_action('init', function() {
    (new WP_Themes_Theme_Preview)->init();
});
