<?php
define( 'DISABLE_WP_CRON', true);
function is_wordpress_org_theme_preview() {
	return true;
}

class WP_Themes_Theme_Preview {
    private $starter_content;

    public function init() {
        $this->set_starter_content();

        if( empty( $this->starter_content)) {
            return;
        }

        $this->set_options();

        add_filter( 'posts_pre_query', [ $this, 'filter_post'], 10, 2 );
        add_action('parse_request', [ $this, 'filter_blog_request' ] );
        add_filter( 'parse_query', [ $this, 'filter_blog_page_query'] );

        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
        remove_action( 'template_redirect', 'redirect_canonical' );

        add_action( 'wp_footer', [ $this, 'debug' ] );
    }

    public function set_starter_content() {
        $starter_content = get_theme_starter_content();

        if( ! empty( $starter_content['posts'])) {
            foreach( array_keys( $starter_content['posts'] ) as $name ) {
                $post_id = $this->generate_id( $name );
                $starter_content['posts'][$name]['ID'] = $post_id;
                $starter_content['mapping'][$name] = $post_id;
                $starter_content['posts'][$name]['post_name'] = $name;
                if( $starter_content['posts'][$name]['post_type'] == 'page') {
                    $starter_content['posts'][$name][ 'comment_status'] = 'closed'; 
                }
            }
        }

        if( ! empty( $starter_content['attachments'])) {
            foreach( array_keys( $starter_content['attachments'] ) as $name ) {
                $attachment_id = $this->generate_id( $name );
                $starter_content['attachments'][$name]['ID'] = $attachment_id;
                $starter_content['mapping'][$name] = $attachment_id;
            }
        }

        array_walk_recursive( $starter_content, function( &$value) use ($starter_content) {
            if ( preg_match( '/^{{(?P<symbol>.+)}}$/', $value, $matches ) ) {
                if ( isset( $starter_content['mapping'][ $matches['symbol'] ] ) ) {
                    $value =  $starter_content['mapping'][ $matches['symbol'] ];
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

    public function filter_post($posts, $query) {
        if(! $query->is_main_query() || empty($this->starter_content['options']['page_on_front'] )) {
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

    private function generate_id( $name = '' ) {
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