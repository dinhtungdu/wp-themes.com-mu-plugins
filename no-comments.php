<?php
/**
 * Plugin Name: Disallow Comments
 * Description: Routes comments to /dev/null, allows us to have an open comment form that does nothing.
 */

function wporg_disallow_comments_blocker( $text ) {
	// Allow authenticated users to add comments
	if ( is_user_logged_in() ) {
		return $text;
	}

	if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		wp_safe_redirect( $_SERVER['HTTP_REFERER'] );
	}
	die( '<h1>Comments are disabled.</h1>' );
}
add_filter( 'pre_comment_content', 'wporg_disallow_comments_blocker' );
