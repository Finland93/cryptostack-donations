<?php
/**
 * Admin settings: per-chain recipient wallets, token selection, locking,
 * and the security layer (capability checks, nonces, validation/sanitization).
 *
 * @package CryptoStack_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CSD_Settings
 */
class CSD_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var CSD_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return CSD_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook everything.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_csd_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_notices', array( $this, 'maybe_treasury_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
	}

	/**
	 * Read settings with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = array(
			'wallets'        => array(),
			'enabled_tokens' => array(),
			'locked'         => false,
			'fee_mode'       => 'inclusive',
			'project_id'     => '',
			'button_label'   => __( 'Donate with crypto', 'cryptostack-donations' ),
			'theme'          => 'auto',
			'accent'         => '',
		);
		$saved = get_option( CSD_OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Add the settings page under Settings.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'CryptoStack Donations', 'cryptostack-donations' ),
			__( 'Crypto Donations', 'cryptostack-donations' ),
			'manage_options',
			'cryptostack-donations',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Admin assets (lock UX).
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function admin_assets( $hook ) {
		if ( 'settings_page_cryptostack-donations' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'csd-admin',
			CSD_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			CSD_VERSION,
			true
		);
		wp_enqueue_style(
			'csd-admin',
			CSD_URL . 'assets/css/donation.css',
			array(),
			CSD_VERSION
		);
	}

	/**
	 * Render the settings form.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cryptostack-donations' ) );
		}

		$s      = self::get();
		$chains = csd_get_chains();
		$locked = ! empty( $s['locked'] );

		// Group chains by family so the EVM address applies to all EVM chains.
		?>
		<div class="wrap csd-settings">
			<h1><?php esc_html_e( 'CryptoStack Donations', 'cryptostack-donations' ); ?></h1>

			<p class="description">
				<?php
				printf(
					/* translators: %s: fee percentage. */
					esc_html__( 'Donations go directly on-chain to the wallets you configure. A platform fee of %s is added to support development. No smart contracts, no middleman, non-custodial.', 'cryptostack-donations' ),
					esc_html( ( CSD_PLATFORM_FEE_BPS / 100 ) . '%' )
				);
				?>
			</p>

			<?php if ( $locked ) : ?>
				<div class="notice notice-info inline">
					<p>
						<strong><?php esc_html_e( 'Your wallet addresses are locked. 🔒', 'cryptostack-donations' ); ?></strong>
						<?php esc_html_e( 'They cannot be changed until you click “Unlock wallet addresses” at the bottom of this page. This protects your donations from accidental or unauthorized edits.', 'cryptostack-donations' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="csd_save_settings" />
				<?php wp_nonce_field( 'csd_save_settings', 'csd_nonce' ); ?>

				<h2><?php esc_html_e( 'Connection', 'cryptostack-donations' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="csd_project_id"><?php esc_html_e( 'WalletConnect / Reown Project ID', 'cryptostack-donations' ); ?></label>
						</th>
						<td>
							<input type="text" id="csd_project_id" name="project_id" class="regular-text"
								value="<?php echo esc_attr( $s['project_id'] ); ?>" />
							<p class="description">
								<?php
								echo wp_kses_post(
									__( 'Create a free project at <code>dashboard.reown.com</code> and paste the Project ID here. Required for wallet connections.', 'cryptostack-donations' )
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Recipient wallets', 'cryptostack-donations' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Fill in only the wallets you want to accept. A chain appears to donors only if you provide an address for it.', 'cryptostack-donations' ); ?>
				</p>

				<?php
				// EVM: one address used for all EVM chains.
				$evm_addr = isset( $s['wallets']['evm'] ) ? $s['wallets']['evm'] : '';
				$sol_addr = isset( $s['wallets']['solana'] ) ? $s['wallets']['solana'] : '';
				$btc_addr = isset( $s['wallets']['bitcoin'] ) ? $s['wallets']['bitcoin'] : '';
				$ro       = $locked ? 'readonly disabled' : '';
				?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="csd_evm"><?php esc_html_e( 'EVM address (ETH, Polygon, Base, BNB)', 'cryptostack-donations' ); ?></label></th>
						<td>
							<input type="text" id="csd_evm" name="wallet_evm" class="regular-text code"
								placeholder="0x..." value="<?php echo esc_attr( $evm_addr ); ?>" <?php echo esc_attr( $ro ); ?> />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="csd_sol"><?php esc_html_e( 'Solana address', 'cryptostack-donations' ); ?></label></th>
						<td>
							<input type="text" id="csd_sol" name="wallet_solana" class="regular-text code"
								placeholder="Base58..." value="<?php echo esc_attr( $sol_addr ); ?>" <?php echo esc_attr( $ro ); ?> />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="csd_btc"><?php esc_html_e( 'Bitcoin address (Native SegWit)', 'cryptostack-donations' ); ?></label></th>
						<td>
							<input type="text" id="csd_btc" name="wallet_bitcoin" class="regular-text code"
								placeholder="bc1q..." value="<?php echo esc_attr( $btc_addr ); ?>" <?php echo esc_attr( $ro ); ?> />
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Accepted tokens', 'cryptostack-donations' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Native coins are always accepted. You may additionally enable these vetted stablecoins. Only tokens on this curated list can ever be used — arbitrary/spam tokens are never accepted.', 'cryptostack-donations' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<?php foreach ( $chains as $chain_key => $chain ) : ?>
						<?php if ( empty( $chain['tokens'] ) ) { continue; } ?>
						<tr>
							<th scope="row"><?php echo esc_html( $chain['label'] ); ?></th>
							<td>
								<?php foreach ( $chain['tokens'] as $sym => $tok ) : ?>
									<?php
									$checked = ! empty( $s['enabled_tokens'][ $chain_key ] )
										&& in_array( $sym, (array) $s['enabled_tokens'][ $chain_key ], true );
									?>
									<label style="margin-right:14px;">
										<input type="checkbox"
											name="tokens[<?php echo esc_attr( $chain_key ); ?>][]"
											value="<?php echo esc_attr( $sym ); ?>"
											<?php checked( $checked ); ?> <?php echo $locked ? 'disabled' : ''; ?> />
										<?php echo esc_html( $sym ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Appearance & behaviour', 'cryptostack-donations' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="csd_label"><?php esc_html_e( 'Button label', 'cryptostack-donations' ); ?></label></th>
						<td><input type="text" id="csd_label" name="button_label" class="regular-text" value="<?php echo esc_attr( $s['button_label'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="csd_theme"><?php esc_html_e( 'Theme', 'cryptostack-donations' ); ?></label></th>
						<td>
							<select id="csd_theme" name="theme">
								<option value="auto" <?php selected( $s['theme'], 'auto' ); ?>><?php esc_html_e( 'Auto (follow visitor’s system)', 'cryptostack-donations' ); ?></option>
								<option value="light" <?php selected( $s['theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'cryptostack-donations' ); ?></option>
								<option value="dark" <?php selected( $s['theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'cryptostack-donations' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Controls the donation widget’s light/dark appearance on your site.', 'cryptostack-donations' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="csd_accent"><?php esc_html_e( 'Accent color', 'cryptostack-donations' ); ?></label></th>
						<td>
							<input type="text" id="csd_accent" name="accent" class="csd-color-field"
								value="<?php echo esc_attr( $s['accent'] ); ?>"
								data-default-color="#6d28d9" placeholder="#6d28d9" />
							<p class="description"><?php esc_html_e( 'Used for the Donate button and highlights. Leave empty for the default purple.', 'cryptostack-donations' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fee mode', 'cryptostack-donations' ); ?></th>
						<td>
							<label><input type="radio" name="fee_mode" value="inclusive" <?php checked( $s['fee_mode'], 'inclusive' ); ?> />
								<?php esc_html_e( 'Inclusive — fee taken from the donation amount (recipient gets 99%).', 'cryptostack-donations' ); ?></label><br/>
							<label><input type="radio" name="fee_mode" value="on_top" <?php checked( $s['fee_mode'], 'on_top' ); ?> />
								<?php esc_html_e( 'On top — donor pays an extra 1% so the recipient gets 100%.', 'cryptostack-donations' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Security lock', 'cryptostack-donations' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'You do not need to do anything here — your wallet addresses lock automatically every time you save. To change them later, use the Unlock button below. Note: a lock cannot stop someone who already has full administrator or server access — that is true of every plugin.', 'cryptostack-donations' ); ?>
				</p>

				<p class="submit">
					<button type="submit" name="csd_action" value="save" class="button button-primary">
						<?php esc_html_e( 'Save settings', 'cryptostack-donations' ); ?>
					</button>
					<?php if ( $locked ) : ?>
						<button type="submit" name="csd_action" value="unlock" class="button button-secondary csd-unlock-btn" style="margin-left:8px;">
							<?php esc_html_e( 'Unlock wallet addresses', 'cryptostack-donations' ); ?>
						</button>
					<?php endif; ?>
				</p>
				<p class="description">
					<?php
					if ( $locked ) {
						esc_html_e( 'Saving keeps your addresses locked. To change an address: click “Unlock wallet addresses”, edit, then Save settings again.', 'cryptostack-donations' );
					} else {
						esc_html_e( 'When you save, your wallet addresses are automatically locked to protect them.', 'cryptostack-donations' );
					}
					?>
				</p>
			</form>

			<hr/>
			<h2><?php esc_html_e( 'How to display the donation widget', 'cryptostack-donations' ); ?></h2>
			<p>
				<?php esc_html_e( 'Use any of these:', 'cryptostack-donations' ); ?>
			</p>
			<ul style="list-style:disc;margin-left:20px;">
				<li><?php esc_html_e( 'Block editor: add the “Crypto Donation” block.', 'cryptostack-donations' ); ?></li>
				<li><?php esc_html_e( 'Shortcode:', 'cryptostack-donations' ); ?> <code>[crypto_donate]</code> <?php esc_html_e( 'or', 'cryptostack-donations' ); ?> <code>[crypto_donate amount="25" label="Support us"]</code></li>
				<li><?php esc_html_e( 'Widgets: add the “Crypto Donation” widget to any sidebar.', 'cryptostack-donations' ); ?></li>
			</ul>

			<hr/>
			<h2><?php esc_html_e( 'Custom CSS (advanced)', 'cryptostack-donations' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Want full control over the look? Add CSS in Appearance → Customize → Additional CSS. The widget exposes these CSS variables and classes:', 'cryptostack-donations' ); ?>
			</p>
			<p><?php esc_html_e( 'Variables (set them on .csd-widget):', 'cryptostack-donations' ); ?></p>
			<pre style="background:#1e1e1e;color:#e6e6e6;padding:12px;border-radius:6px;overflow:auto;">.csd-widget {
  --csd-accent: #6d28d9;      /* buttons & highlights */
  --csd-accent-2: #8b5cf6;    /* gradient end */
  --csd-bg: #ffffff;          /* card background */
  --csd-fg: #0f172a;          /* text */
  --csd-muted: #64748b;       /* labels / secondary text */
  --csd-border: #e6e8ee;      /* borders */
  --csd-field-bg: #ffffff;    /* inputs/selects */
  --csd-radius: 14px;         /* card corners */
}</pre>
			<p><?php esc_html_e( 'Useful selectors:', 'cryptostack-donations' ); ?>
				<code>.csd-card</code>, <code>.csd-donate-btn</code>, <code>.csd-wallet-btn</code>,
				<code>.csd-select</code>, <code>.csd-input</code>, <code>.csd-label</code>,
				<code>.csd-status--ok</code>, <code>.csd-status--err</code>.
			</p>
			<p class="description">
				<?php esc_html_e( 'Example — make the Donate button bright green with square corners:', 'cryptostack-donations' ); ?>
			</p>
			<pre style="background:#1e1e1e;color:#e6e6e6;padding:12px;border-radius:6px;overflow:auto;">.csd-widget { --csd-accent: #16a34a; --csd-accent-2: #22c55e; }
.csd-donate-btn { border-radius: 4px; }</pre>
		</div>
		<?php
	}

	/**
	 * Handle the settings save: capability + nonce + validation.
	 *
	 * @return void
	 */
	public function handle_save() {
		// 1) Capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cryptostack-donations' ) );
		}

		// 2) Nonce.
		$nonce = isset( $_POST['csd_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['csd_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'csd_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'cryptostack-donations' ) );
		}

		$current = self::get();
		$was_locked = ! empty( $current['locked'] );

		$action = isset( $_POST['csd_action'] ) ? sanitize_key( wp_unslash( $_POST['csd_action'] ) ) : 'save';

		// "Unlock" simply re-enables editing of the wallet fields. It changes
		// nothing else, so the owner can correct an address and save again.
		if ( 'unlock' === $action ) {
			$current['locked'] = false;
			update_option( CSD_OPTION_KEY, $current );
			wp_safe_redirect( admin_url( 'options-general.php?page=cryptostack-donations&csd_unlocked=1' ) );
			exit;
		}

		// 3) Build the new settings, validating every field.
		$new = $current;

		$new['project_id']   = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';
		$new['button_label'] = isset( $_POST['button_label'] ) ? sanitize_text_field( wp_unslash( $_POST['button_label'] ) ) : $current['button_label'];

		$theme         = isset( $_POST['theme'] ) ? sanitize_key( wp_unslash( $_POST['theme'] ) ) : 'auto';
		$new['theme']  = in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';

		$fee_mode        = isset( $_POST['fee_mode'] ) ? sanitize_key( wp_unslash( $_POST['fee_mode'] ) ) : 'inclusive';
		$new['fee_mode'] = in_array( $fee_mode, array( 'inclusive', 'on_top' ), true ) ? $fee_mode : 'inclusive';

		$accent        = isset( $_POST['accent'] ) ? sanitize_hex_color( wp_unslash( $_POST['accent'] ) ) : '';
		$new['accent'] = $accent ? $accent : '';

		// Wallets are only writable when NOT currently locked.
		if ( ! $was_locked ) {
			$wallets = array();

			$evm = isset( $_POST['wallet_evm'] ) ? sanitize_text_field( wp_unslash( $_POST['wallet_evm'] ) ) : '';
			$sol = isset( $_POST['wallet_solana'] ) ? sanitize_text_field( wp_unslash( $_POST['wallet_solana'] ) ) : '';
			$btc = isset( $_POST['wallet_bitcoin'] ) ? sanitize_text_field( wp_unslash( $_POST['wallet_bitcoin'] ) ) : '';

			$errors = array();

			if ( '' !== $evm ) {
				if ( self::is_valid_evm( $evm ) ) {
					$wallets['evm'] = $evm;
				} else {
					$errors[] = __( 'EVM address looks invalid.', 'cryptostack-donations' );
				}
			}
			if ( '' !== $sol ) {
				if ( self::is_valid_solana( $sol ) ) {
					$wallets['solana'] = $sol;
				} else {
					$errors[] = __( 'Solana address looks invalid.', 'cryptostack-donations' );
				}
			}
			if ( '' !== $btc ) {
				if ( self::is_valid_btc_bech32( $btc ) ) {
					$wallets['bitcoin'] = $btc;
				} else {
					$errors[] = __( 'Bitcoin address must be a valid Native SegWit (bc1...) address.', 'cryptostack-donations' );
				}
			}

			if ( ! empty( $errors ) ) {
				set_transient( 'csd_admin_error', implode( ' ', $errors ), 30 );
				wp_safe_redirect( admin_url( 'options-general.php?page=cryptostack-donations&csd_error=1' ) );
				exit;
			}

			$new['wallets'] = $wallets;

			// Tokens (only persisted when editing is allowed).
			$enabled = array();
			$chains  = csd_get_chains();
			if ( isset( $_POST['tokens'] ) && is_array( $_POST['tokens'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$raw = wp_unslash( $_POST['tokens'] );
				foreach ( $raw as $chain_key => $syms ) {
					$chain_key = sanitize_key( $chain_key );
					if ( ! isset( $chains[ $chain_key ] ) ) {
						continue;
					}
					$valid = array_keys( $chains[ $chain_key ]['tokens'] );
					foreach ( (array) $syms as $sym ) {
						$sym = sanitize_text_field( $sym );
						if ( in_array( $sym, $valid, true ) ) {
							$enabled[ $chain_key ][] = $sym;
						}
					}
				}
			}
			$new['enabled_tokens'] = $enabled;
		}

		// Saving always locks the wallet addresses (whenever any are configured)
		// so they are protected by default. Use the "Unlock" button to edit them.
		$new['locked'] = ! empty( $new['wallets'] );

		update_option( CSD_OPTION_KEY, $new );

		wp_safe_redirect( admin_url( 'options-general.php?page=cryptostack-donations&csd_saved=1' ) );
		exit;
	}

	/**
	 * Treasury integrity / saved notices.
	 *
	 * @return void
	 */
	public function maybe_treasury_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_cryptostack-donations' !== $screen->id ) {
			return;
		}

		if ( isset( $_GET['csd_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'cryptostack-donations' ) . '</p></div>';
		}

		if ( isset( $_GET['csd_unlocked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Wallet addresses unlocked. Edit them, then click “Save settings” — saving will lock them again.', 'cryptostack-donations' ) . '</p></div>';
		}

		if ( isset( $_GET['csd_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$msg = get_transient( 'csd_admin_error' );
			if ( $msg ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
				delete_transient( 'csd_admin_error' );
			}
		}

		if ( ! csd_treasury_is_intact() ) {
			echo '<div class="notice notice-error"><p><strong>'
				. esc_html__( 'CryptoStack Donations: treasury integrity check failed.', 'cryptostack-donations' )
				. '</strong> '
				. esc_html__( 'The platform fee will be disabled until the configuration is restored.', 'cryptostack-donations' )
				. '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Validators. Format-level in PHP; the JS engine does deeper checks
	 * (EIP-55 checksum via keccak, base58 byte-length, bech32 checksum)
	 * before any transaction is built.
	 * ------------------------------------------------------------------ */

	/**
	 * Basic EVM address format (0x + 40 hex). Full EIP-55 checksum is
	 * validated client-side where keccak256 is available.
	 *
	 * @param string $a Address.
	 * @return bool
	 */
	public static function is_valid_evm( $a ) {
		return (bool) preg_match( '/^0x[a-fA-F0-9]{40}$/', $a );
	}

	/**
	 * Solana address: base58, decodes to 32 bytes.
	 *
	 * @param string $a Address.
	 * @return bool
	 */
	public static function is_valid_solana( $a ) {
		if ( ! preg_match( '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $a ) ) {
			return false;
		}
		// Deeper byte-length check needs bcmath; if missing, accept the
		// format match (the JS engine re-validates before sending).
		if ( ! function_exists( 'bcadd' ) ) {
			return true;
		}
		$decoded = self::base58_decode( $a );
		return ( false !== $decoded && 32 === strlen( $decoded ) );
	}

	/**
	 * Bitcoin Native SegWit (bech32, bc1q...). Reasonable format check;
	 * full bech32 checksum is verified by the wallet and JS engine.
	 *
	 * @param string $a Address.
	 * @return bool
	 */
	public static function is_valid_btc_bech32( $a ) {
		$a = strtolower( $a );
		// v0 P2WPKH = 42 chars, P2WSH = 62 chars; allow the valid range.
		return (bool) preg_match( '/^bc1q[023456789acdefghjklmnpqrstuvwxyz]{38,58}$/', $a );
	}

	/**
	 * Minimal base58 decoder for validation.
	 *
	 * @param string $input Base58 string.
	 * @return string|false Raw bytes or false.
	 */
	private static function base58_decode( $input ) {
		$alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
		$base     = 58;
		$decoded  = '0';

		for ( $i = 0, $len = strlen( $input ); $i < $len; $i++ ) {
			$pos = strpos( $alphabet, $input[ $i ] );
			if ( false === $pos ) {
				return false;
			}
			$decoded = bcadd( bcmul( $decoded, (string) $base ), (string) $pos );
		}

		$hex = '';
		while ( bccomp( $decoded, '0' ) > 0 ) {
			$rem     = bcmod( $decoded, '256' );
			$decoded = bcdiv( $decoded, '256', 0 );
			$hex     = chr( (int) $rem ) . $hex;
		}

		// Restore leading zero bytes (each leading '1' is a 0x00 byte).
		for ( $i = 0; $i < strlen( $input ) && '1' === $input[ $i ]; $i++ ) {
			$hex = "\x00" . $hex;
		}

		return $hex;
	}
}
