<?php
/**
 * Frontend rendering: shortcode, Gutenberg block, asset loading, and the
 * configuration object passed to the JavaScript donation engine.
 *
 * @package CryptoStack_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CSD_Render
 */
class CSD_Render {

	/**
	 * Singleton.
	 *
	 * @var CSD_Render|null
	 */
	private static $instance = null;

	/**
	 * Whether the engine config was already printed.
	 *
	 * @var bool
	 */
	private $printed_config = false;

	/**
	 * Get instance.
	 *
	 * @return CSD_Render
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	private function __construct() {
		add_shortcode( 'crypto_donate', array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (but do not enqueue) frontend assets. They are enqueued
	 * on demand when a widget is rendered, to avoid loading the wallet
	 * stack on every page.
	 *
	 * @return void
	 */
	public function register_assets() {
		// The Reown AppKit bundle is built locally (see README). It exposes
		// window.CSDAppKit. We register it as a dependency of the engine.
		wp_register_script(
			'csd-appkit',
			CSD_URL . 'build/appkit-bundle.js',
			array(),
			CSD_VERSION,
			true
		);

		wp_register_script(
			'csd-engine',
			CSD_URL . 'assets/js/donation-engine.js',
			array( 'csd-appkit' ),
			CSD_VERSION,
			true
		);

		wp_register_style(
			'csd-style',
			CSD_URL . 'assets/css/donation.css',
			array(),
			CSD_VERSION
		);
	}

	/**
	 * Ensure assets + the shared config are present once a widget renders.
	 *
	 * @return void
	 */
	private function ensure_runtime() {
		wp_enqueue_style( 'csd-style' );
		wp_enqueue_script( 'csd-appkit' );
		wp_enqueue_script( 'csd-engine' );

		if ( $this->printed_config ) {
			return;
		}
		$this->printed_config = true;

		wp_add_inline_script(
			'csd-engine',
			'window.CSD_CONFIG = ' . wp_json_encode( $this->build_config() ) . ';',
			'before'
		);
	}

	/**
	 * Build the configuration object handed to the JS engine.
	 *
	 * Only chains that (a) have a recipient address configured by the site
	 * owner AND (b) have an intact hardcoded treasury are exposed.
	 *
	 * @return array
	 */
	private function build_config() {
		$s            = CSD_Settings::get();
		$all_chains   = csd_get_chains();
		$intact       = csd_treasury_is_intact();
		$out_chains   = array();

		foreach ( $all_chains as $key => $chain ) {
			$family    = $chain['family'];
			$recipient = isset( $s['wallets'][ $family ] ) ? $s['wallets'][ $family ] : '';

			if ( '' === $recipient ) {
				continue; // Site owner did not enable this family.
			}

			// Determine enabled tokens for this chain (subset of allow-list).
			$tokens = array();
			if ( ! empty( $chain['tokens'] ) && ! empty( $s['enabled_tokens'][ $key ] ) ) {
				foreach ( (array) $s['enabled_tokens'][ $key ] as $sym ) {
					if ( isset( $chain['tokens'][ $sym ] ) ) {
						$tokens[ $sym ] = $chain['tokens'][ $sym ];
					}
				}
			}

			$out_chains[ $key ] = array(
				'key'       => $key,
				'label'     => $chain['label'],
				'family'    => $family,
				'native'    => $chain['native'],
				'caip'      => $chain['caip'],
				'chainId'   => isset( $chain['chain_id'] ) ? $chain['chain_id'] : null,
				'decimals'  => $chain['decimals'],
				'explorer'  => $chain['explorer'],
				'recipient' => $recipient,
				// Treasury only included when integrity check passes.
				'treasury'  => $intact ? csd_treasury_for_family( $family ) : null,
				'tokens'    => $tokens,
			);
		}

		return array(
			'projectId'  => $s['project_id'],
			'feeBps'     => $intact ? (int) CSD_PLATFORM_FEE_BPS : 0,
			'feeMode'    => $s['fee_mode'], // inclusive | on_top.
			'theme'      => $s['theme'],
			'chains'     => $out_chains,
			'i18n'       => array(
				'connect'      => __( 'Connect wallet', 'cryptostack-donations' ),
				'disconnect'   => __( 'Disconnect', 'cryptostack-donations' ),
				'connected'    => __( 'Connected', 'cryptostack-donations' ),
				'notConnected' => __( 'Wallet not connected', 'cryptostack-donations' ),
				'donate'       => __( 'Donate', 'cryptostack-donations' ),
				'amount'       => __( 'Amount', 'cryptostack-donations' ),
				'chain'        => __( 'Network', 'cryptostack-donations' ),
				'asset'        => __( 'Asset', 'cryptostack-donations' ),
				'processing'   => __( 'Processing…', 'cryptostack-donations' ),
				'thankYou'     => __( 'Thank you for your donation!', 'cryptostack-donations' ),
				'feeNotice'    => __( 'A 1% platform fee supports development.', 'cryptostack-donations' ),
				'twoStep'      => __( 'Your wallet may ask for two confirmations (donation + fee).', 'cryptostack-donations' ),
				'noChains'     => __( 'Donations are not configured yet.', 'cryptostack-donations' ),
				'error'        => __( 'Something went wrong. Please try again.', 'cryptostack-donations' ),
				'viewTx'       => __( 'View transaction', 'cryptostack-donations' ),
				'transactions' => __( 'transactions', 'cryptostack-donations' ),
			),
		);
	}

	/**
	 * Render one donation widget instance.
	 *
	 * @param array $args Instance args (amount, label).
	 * @return string
	 */
	public function render_widget( $args = array() ) {
		$s = CSD_Settings::get();

		// If nothing is configured, render a graceful placeholder for admins
		// and nothing for visitors.
		$config = $this->build_config();
		if ( empty( $config['chains'] ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="csd-widget csd-empty">'
					. esc_html__( 'CryptoStack Donations: add a wallet address in Settings to show the donation button.', 'cryptostack-donations' )
					. '</div>';
			}
			return '';
		}

		$this->ensure_runtime();

		$label   = isset( $args['label'] ) && '' !== $args['label'] ? $args['label'] : $s['button_label'];
		$amount  = isset( $args['amount'] ) ? $args['amount'] : '';
		$uid     = 'csd-' . wp_generate_uuid4();

		// Optional custom accent color -> inline CSS variables.
		$style = '';
		if ( ! empty( $s['accent'] ) ) {
			$accent2 = self::lighten_hex( $s['accent'], 16 );
			$style   = '--csd-accent:' . $s['accent'] . ';--csd-accent-2:' . $accent2 . ';';
		}

		ob_start();
		?>
		<div class="csd-widget" data-csd-widget="<?php echo esc_attr( $uid ); ?>"
			data-default-amount="<?php echo esc_attr( $amount ); ?>"
			data-theme="<?php echo esc_attr( $s['theme'] ); ?>"
			<?php if ( $style ) : ?>style="<?php echo esc_attr( $style ); ?>"<?php endif; ?>>
			<div class="csd-card csd-card--loading" aria-busy="true">
				<div class="csd-skeleton csd-skeleton--row"></div>
				<div class="csd-skeleton csd-skeleton--input"></div>
				<button type="button" class="csd-donate-btn" disabled>
					<?php echo esc_html( $label ); ?>
				</button>
			</div>
			<noscript>
				<p class="csd-fee-note"><?php esc_html_e( 'Please enable JavaScript to donate with crypto.', 'cryptostack-donations' ); ?></p>
			</noscript>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Lighten a #rrggbb hex color by a percentage toward white.
	 *
	 * @param string $hex     Hex color (#rrggbb).
	 * @param int    $percent 0-100.
	 * @return string Hex color.
	 */
	private static function lighten_hex( $hex, $percent ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '#' . ( $hex ? $hex : '6d28d9' );
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$p = max( 0, min( 100, (int) $percent ) ) / 100;
		$r = (int) round( $r + ( 255 - $r ) * $p );
		$g = (int) round( $g + ( 255 - $g ) * $p );
		$b = (int) round( $b + ( 255 - $b ) * $p );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Shortcode handler: [crypto_donate amount="25" label="Support us"].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'amount' => '',
				'label'  => '',
			),
			$atts,
			'crypto_donate'
		);

		return $this->render_widget(
			array(
				'amount' => preg_replace( '/[^0-9.]/', '', (string) $atts['amount'] ),
				'label'  => sanitize_text_field( $atts['label'] ),
			)
		);
	}

	/**
	 * Register the Gutenberg block (server-rendered).
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Editor script.
		wp_register_script(
			'csd-block',
			CSD_URL . 'blocks/donation/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			CSD_VERSION,
			true
		);

		register_block_type(
			CSD_DIR . 'blocks/donation',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$args = array(
			'amount' => isset( $attributes['amount'] ) ? preg_replace( '/[^0-9.]/', '', (string) $attributes['amount'] ) : '',
			'label'  => isset( $attributes['label'] ) ? sanitize_text_field( $attributes['label'] ) : '',
		);
		return $this->render_widget( $args );
	}
}
