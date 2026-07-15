/**
 * CryptoStack Donations — admin settings UX.
 *
 * Dependency-free. Two jobs:
 *   1. Make the "Unlock wallet addresses" button deliberate. Unlocking is the
 *      security-sensitive direction, so it asks for confirmation. (Locking is
 *      automatic on save and needs no interaction.)
 *   2. Give gentle, non-blocking format hints on the address fields while they
 *      are editable. Authoritative validation still happens server-side (PHP)
 *      and again in the donation engine before any transaction is built.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Confirm before unlocking the wallet fields.
		var unlockBtn = document.querySelector( 'button[name="csdon_action"][value="unlock"]' );
		if ( unlockBtn ) {
			unlockBtn.addEventListener( 'click', function ( e ) {
				var ok = window.confirm(
					'Unlock your wallet addresses for editing?\n\n' +
					'They are currently locked for safety. After you save again, they ' +
					'will be locked automatically. Only unlock if you need to change an address now.'
				);
				if ( ! ok ) {
					e.preventDefault();
				}
			} );
		}

		/* --------------------------------------------------------------- *
		 * Non-blocking address format hints.
		 * --------------------------------------------------------------- */
		var checks = [
			{ id: 'csdon_evm', re: /^0x[a-fA-F0-9]{40}$/, msg: 'Expected 0x followed by 40 hex characters.' },
			{ id: 'csdon_sol', re: /^[1-9A-HJ-NP-Za-km-z]{32,44}$/, msg: 'Expected a base58 Solana address (32–44 chars).' },
			{ id: 'csdon_btc', re: /^bc1q[02-9ac-hj-np-z]{38,58}$/, msg: 'Expected a Native SegWit address starting with bc1q.' }
		];

		checks.forEach( function ( c ) {
			var input = document.getElementById( c.id );
			if ( ! input || input.disabled || input.readOnly ) {
				return;
			}

			function validate() {
				var v = input.value.trim();
				var warn = input.parentNode.querySelector( '.csdon-field-warning' );
				if ( '' === v || c.re.test( v ) ) {
					input.classList.remove( 'csdon-invalid' );
					if ( warn ) {
						warn.parentNode.removeChild( warn );
					}
					return;
				}
				input.classList.add( 'csdon-invalid' );
				if ( ! warn ) {
					warn = document.createElement( 'span' );
					warn.className = 'csdon-field-warning';
					input.parentNode.appendChild( warn );
				}
				warn.textContent = c.msg;
			}

			input.addEventListener( 'blur', validate );
			input.addEventListener( 'input', function () {
				if ( input.classList.contains( 'csdon-invalid' ) ) {
					validate();
				}
			} );
		} );
		/* --------------------------------------------------------------- *
		 * Accent color picker (WordPress wp-color-picker).
		 * --------------------------------------------------------------- */
		if ( window.jQuery && window.jQuery.fn && window.jQuery.fn.wpColorPicker ) {
			window.jQuery( '.csdon-color-field' ).wpColorPicker();
		}
	} );
} )();
