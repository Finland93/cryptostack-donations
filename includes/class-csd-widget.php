<?php
/**
 * Classic sidebar widget wrapper around the donation widget.
 *
 * @package CryptoStack_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CSD_Widget
 */
class CSD_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'csd_widget',
			__( 'Crypto Donation', 'cryptostack-donations' ),
			array(
				'description' => __( 'A multi-chain crypto donation button (EVM, Solana, Bitcoin).', 'cryptostack-donations' ),
			)
		);
	}

	/**
	 * Front-end display.
	 *
	 * @param array $args     Sidebar args.
	 * @param array $instance Widget settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$title  = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$label  = ! empty( $instance['label'] ) ? $instance['label'] : '';
		$amount = ! empty( $instance['amount'] ) ? $instance['amount'] : '';

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $title ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// render_widget() returns markup whose dynamic parts are already
		// escaped (esc_html/esc_attr) inside CSD_Render. Build then echo.
		$html = CSD_Render::instance()->render_widget(
			array(
				'label'  => sanitize_text_field( $label ),
				'amount' => sanitize_text_field( $amount ),
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Settings form.
	 *
	 * @param array $instance Current settings.
	 * @return string
	 */
	public function form( $instance ) {
		$title  = isset( $instance['title'] ) ? $instance['title'] : '';
		$label  = isset( $instance['label'] ) ? $instance['label'] : '';
		$amount = isset( $instance['amount'] ) ? $instance['amount'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'cryptostack-donations' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'label' ) ); ?>"><?php esc_html_e( 'Button label:', 'cryptostack-donations' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'label' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'label' ) ); ?>" type="text"
				value="<?php echo esc_attr( $label ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'amount' ) ); ?>"><?php esc_html_e( 'Default amount (optional):', 'cryptostack-donations' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'amount' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'amount' ) ); ?>" type="text"
				value="<?php echo esc_attr( $amount ); ?>" />
		</p>
		<?php
		return '';
	}

	/**
	 * Sanitize on save.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance           = array();
		$instance['title']  = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['label']  = isset( $new_instance['label'] ) ? sanitize_text_field( $new_instance['label'] ) : '';
		$instance['amount'] = isset( $new_instance['amount'] ) ? preg_replace( '/[^0-9.]/', '', $new_instance['amount'] ) : '';
		return $instance;
	}
}
