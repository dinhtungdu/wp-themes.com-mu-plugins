<?php
/**
 * Plugin Name: Theme Sync for wp-themes.com
 * Description: Allows WordPress.org to trigger a remote theme install/delete/update.
 * Author: Dion Hulse
 */

class WP_Themes_Theme_Sync_Update {

	function __construct() {
		// Add endpoints for W.org to push updates
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );

		// Enable auto-updates for themes, in addition to W.org pushing updates
		add_filter( 'auto_update_theme', '__return_true' );
	}

	function rest_api_init() {
		register_rest_route( 'wp-themes.com/v1', '/update/(?P<slug>[a-z0-9_-]+)/(?P<version>[^/]+)', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'update' ),
			'permission_callback' => array( $this, 'permission_callback' ),
		) );
		register_rest_route( 'wp-themes.com/v1', '/remove/(?P<slug>[a-z0-9_-]+)', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'remove' ),
			'permission_callback' => array( $this, 'permission_callback' ),
		) );
	}

	function permission_callback( $request ) {
		$authorization_header = $request->get_header( 'authorization' );
		$authorization_header = trim( str_ireplace( 'bearer ', '', $authorization_header ) );

		if (
			! $authorization_header ||
			! defined( 'THEME_PREVIEWS_SYNC_SECRET' ) ||
			! hash_equals( THEME_PREVIEWS_SYNC_SECRET, $authorization_header )
		) {
			return new \WP_Error(
				'not_authorized',
				'Sorry! You cannot do that.',
				array( 'status' => \WP_Http::UNAUTHORIZED )
			);
		}

		return true;
	}

	function update( $request ) {
		include_once ABSPATH . 'wp-admin/includes/admin.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$theme_slug = $request['slug'];
		$theme_version = preg_replace( '![^0-9.]!i', '', $request['version'] );

		$theme = wp_get_theme( $theme_slug );

		// If it's a theme update, remove it first.
		if ( $theme->exists() ) {
			// no-op if it's already up to date.
			if ( $theme->get('Version') === $theme_version ) {
				return 'already-up-to-date';
			}

			delete_theme( $theme_slug );
		}

		// Install, not using API here as the API may be cached still.
		$zip_link = "https://downloads.wordpress.org/theme/{$theme_slug}.{$theme_version}.zip";
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );

		return (bool) $upgrader->install( $zip_link );
	}

	function remove( $request ) {
		include_once ABSPATH . 'wp-admin/includes/admin.php';

		$theme_slug = $request['slug'];

		$theme = wp_get_theme( $theme_slug );
		if ( $theme->exists() ) {
			delete_theme( $theme_slug );
		}

		return true;
	}

}
new WP_Themes_Theme_Sync_Update;

