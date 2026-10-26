<?php
/**
 * TintCal uninstall routine.
 *
 * Runs only when the plugin is deleted from the WordPress admin screen.
 * Removes every piece of data the plugin created: options, transients,
 * calendar posts (and their meta), custom capabilities, cached holiday files
 * and the scheduled event.
 *
 * @package TintCal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all TintCal data from the current site.
 *
 * @return void
 */
function tintcal_uninstall_current_site() {
	global $wpdb;

	// 1. Calendar posts (custom post type "tintcal") and their meta.
	$post_ids = get_posts(
		array(
			'post_type'      => 'tintcal',
			'post_status'    => 'any',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'suppress_filters' => true,
		)
	);
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	// 2. Options and transients prefixed with "tintcal_".
	$like_option    = $wpdb->esc_like( 'tintcal_' ) . '%';
	$like_transient = $wpdb->esc_like( '_transient_tintcal_' ) . '%';
	$like_timeout   = $wpdb->esc_like( '_transient_timeout_tintcal_' ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup on uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$like_option,
			$like_transient,
			$like_timeout
		)
	);

	// 3. Custom capabilities added to roles.
	$caps = array(
		'edit_tintcal',
		'read_tintcal',
		'delete_tintcal',
		'edit_tintcals',
		'edit_others_tintcals',
		'publish_tintcals',
		'read_private_tintcals',
		'delete_tintcals',
		'delete_private_tintcals',
		'delete_published_tintcals',
		'delete_others_tintcals',
		'edit_private_tintcals',
		'edit_published_tintcals',
	);
	$wp_roles = wp_roles();
	if ( $wp_roles instanceof WP_Roles ) {
		foreach ( array_keys( $wp_roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	// 4. Scheduled holiday update.
	wp_clear_scheduled_hook( 'tintcal_daily_holiday_update_hook' );

	// 5. Cached holiday JSON files in the uploads directory.
	$upload_dir = wp_upload_dir();
	$holiday_dir = trailingslashit( $upload_dir['basedir'] ) . 'tintcal-holidays';
	if ( is_dir( $holiday_dir ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( WP_Filesystem() ) {
			global $wp_filesystem;
			$wp_filesystem->delete( $holiday_dir, true );
		}
	}
}

if ( is_multisite() ) {
	$tintcal_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $tintcal_site_ids as $tintcal_site_id ) {
		switch_to_blog( (int) $tintcal_site_id );
		tintcal_uninstall_current_site();
		restore_current_blog();
	}
} else {
	tintcal_uninstall_current_site();
}
