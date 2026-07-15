<?php
/**
 * Plugin Name:       CryptoStack Donations
 * Plugin URI:        https://github.com/Finland93/cryptostack-donations
 * Description:        Accept crypto donations on Ethereum/EVM, Solana and Bitcoin with one WalletConnect button. No smart contracts, no middleman, non-custodial. Gutenberg block, shortcode and sidebar widget.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Finland93
 * Author URI:        https://github.com/Finland93
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cryptostack-donations
 * Domain Path:       /languages
 *
 * @package CryptoStack_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'CSDON_VERSION', '0.1.0' );
define( 'CSDON_FILE', __FILE__ );
define( 'CSDON_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSDON_URL', plugin_dir_url( __FILE__ ) );
define( 'CSDON_BASENAME', plugin_basename( __FILE__ ) );

// Option key holding per-site settings (recipient wallets, lock flag, etc.).
define( 'CSDON_OPTION_KEY', 'csdon_settings' );

require_once CSDON_DIR . 'includes/config.php';
require_once CSDON_DIR . 'includes/class-csdon-settings.php';
require_once CSDON_DIR . 'includes/class-csdon-render.php';
require_once CSDON_DIR . 'includes/class-csdon-widget.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function csdon_bootstrap() {
	// Admin settings screen + sanitization + locking.
	CSDON_Settings::instance();

	// Frontend rendering: shortcode, block, asset loading, JS config.
	CSDON_Render::instance();

	// Classic sidebar widget.
	add_action(
		'widgets_init',
		static function () {
			register_widget( 'CSDON_Widget' );
		}
	);

	// Settings link on the Plugins screen.
	add_filter(
		'plugin_action_links_' . CSDON_BASENAME,
		static function ( $links ) {
			$url      = admin_url( 'options-general.php?page=cryptostack-donations' );
			$settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'cryptostack-donations' ) . '</a>';
			array_unshift( $links, $settings );
			return $links;
		}
	);
}
add_action( 'plugins_loaded', 'csdon_bootstrap' );

/**
 * On activation, store default settings if none exist and warn if the
 * treasury integrity check fails (developer misconfiguration).
 *
 * @return void
 */
function csdon_activate() {
	if ( false === get_option( CSDON_OPTION_KEY ) ) {
		add_option(
			CSDON_OPTION_KEY,
			array(
				'wallets'        => array(),  // family => address.
				'enabled_tokens' => array(),  // chain => [SYMBOL,...].
				'locked'         => false,
				'fee_mode'       => 'inclusive', // inclusive | on_top.
				'project_id'     => '',         // Reown / WalletConnect project id.
				'button_label'   => __( 'Donate with crypto', 'cryptostack-donations' ),
				'theme'          => 'auto',     // auto | light | dark.
				'accent'         => '',         // optional hex accent color.
			)
		);
	}

	if ( ! csdon_treasury_is_intact() ) {
		// Surface a persistent admin notice flag; do not block activation.
		update_option( 'csdon_treasury_warning', 1 );
	}
}
register_activation_hook( __FILE__, 'csdon_activate' );
