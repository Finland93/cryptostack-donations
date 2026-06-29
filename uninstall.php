<?php
/**
 * Uninstall cleanup for CryptoStack Donations.
 *
 * Runs only when the plugin is deleted from the WordPress admin. Removes the
 * single options row and any transients the plugin may have left behind. Wallet
 * addresses are stored in that option, so deleting the plugin fully removes
 * them. (Deactivation does NOT remove anything.)
 *
 * @package CryptoStack_Donations
 */

// Exit if accessed directly or not through the uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Keep this in sync with CSD_OPTION_KEY in the main plugin file.
delete_option( 'csd_settings' );

// Clean up transients used for admin notices.
delete_transient( 'csd_admin_error' );

// Multisite: remove the option on every site in the network.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( (array) $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		delete_option( 'csd_settings' );
		delete_transient( 'csd_admin_error' );
		restore_current_blog();
	}
}
