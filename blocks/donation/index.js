/**
 * CryptoStack Donations — block editor registration.
 *
 * Dynamic (server-rendered) block: save() returns null and PHP renders the real
 * widget on the front end via the render_callback. The editor shows a lightweight
 * static preview plus inspector controls for amount and label.
 *
 * Written with wp.element.createElement (no JSX) so it needs no build step.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;

	blocks.registerBlockType( 'cryptostack/donation', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			var label = attributes.label || __( 'Donate with crypto', 'cryptostack-donations' );

			var preview = el(
				'div',
				blockProps,
				el(
					'div',
					{
						style: {
							display: 'inline-flex',
							alignItems: 'center',
							gap: '8px',
							padding: '11px 18px',
							fontSize: '15px',
							fontWeight: 600,
							color: '#ffffff',
							background: '#6d28d9',
							borderRadius: '12px',
						},
					},
					el( 'span', { 'aria-hidden': 'true' }, '\u25C6' ),
					label
				),
				el(
					'p',
					{ style: { margin: '8px 0 0', fontSize: '12px', color: '#64748b' } },
					__( 'A 1% platform fee supports development.', 'cryptostack-donations' )
				),
				attributes.amount
					? el(
						'p',
						{ style: { margin: '4px 0 0', fontSize: '12px', color: '#64748b' } },
						__( 'Suggested amount: ', 'cryptostack-donations' ) + attributes.amount
					)
					: null
			);

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Donation settings', 'cryptostack-donations' ), initialOpen: true },
					el( TextControl, {
						label: __( 'Button label', 'cryptostack-donations' ),
						value: attributes.label,
						onChange: function ( value ) {
							setAttributes( { label: value } );
						},
						help: __( 'Leave empty to use the default from plugin settings.', 'cryptostack-donations' ),
					} ),
					el( TextControl, {
						label: __( 'Suggested amount (optional)', 'cryptostack-donations' ),
						type: 'text',
						inputMode: 'decimal',
						value: attributes.amount,
						onChange: function ( value ) {
							// Keep digits and a single decimal point only.
							var clean = ( value || '' ).replace( /[^0-9.]/g, '' );
							setAttributes( { amount: clean } );
						},
						help: __( 'Pre-fills the amount field for the donor. They can change it.', 'cryptostack-donations' ),
					} )
				)
			);

			return el( element.Fragment, {}, inspector, preview );
		},

		// Dynamic block — rendered by PHP.
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
