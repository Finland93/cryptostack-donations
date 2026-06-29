/**
 * CryptoStack Donations — donation engine.
 *
 * Splits every donation into two on-chain transfers WITHOUT any smart
 * contract:
 *   - recipient (the site owner's configured wallet)
 *   - treasury  (the hardcoded 1% platform fee wallet)
 *
 * Per chain:
 *   EVM     : EIP-5792 wallet_sendCalls (ONE approval) when the wallet
 *             supports atomic batching; otherwise TWO eth_sendTransaction
 *             calls (fee + donation) as a documented fallback.
 *   Solana  : ONE transaction with TWO SystemProgram/Token transfer
 *             instructions -> ONE signature. No program deployed.
 *   Bitcoin : ONE transaction with TWO outputs -> ONE signature (PSBT).
 *
 * Anti-scam guarantee: this engine ONLY ever constructs (a) native
 * transfers and (b) `transfer` calls to the configured recipient or the
 * hardcoded treasury for tokens on the curated allow-list. It NEVER calls
 * `approve`, never touches arbitrary contracts, and never accepts an
 * arbitrary token. The donor always reviews and confirms in their wallet.
 *
 * Wallet connectivity is provided by window.CSDAppKit (a thin wrapper around
 * Reown AppKit / WalletConnect, built locally — see assets/src + README).
 */
( function () {
	'use strict';

	var CFG = window.CSD_CONFIG || null;
	var AK  = window.CSDAppKit || null;

	if ( ! CFG ) {
		return;
	}

	/* ------------------------------------------------------------------ *
	 * Amount math — always in smallest units via BigInt. Never floats.
	 * ------------------------------------------------------------------ */

	/**
	 * Parse a decimal string into the smallest integer unit.
	 * e.g. parseUnits("0.5", 9) -> 500000000n
	 *
	 * @param {string} value    Decimal string.
	 * @param {number} decimals Token decimals.
	 * @returns {bigint}
	 */
	function parseUnits( value, decimals ) {
		value = String( value == null ? '' : value ).trim();
		if ( ! value ) {
			return 0n;
		}
		var neg = value.startsWith( '-' );
		if ( neg ) {
			value = value.slice( 1 );
		}
		var parts = value.split( '.' );
		var whole = parts[ 0 ] || '0';
		var frac  = ( parts[ 1 ] || '' ).slice( 0, decimals );
		while ( frac.length < decimals ) {
			frac += '0';
		}
		var combined = ( whole + frac ).replace( /^0+(?=\d)/, '' );
		var result   = BigInt( combined || '0' );
		return neg ? -result : result;
	}

	/**
	 * Format smallest units back to a human decimal string (for display).
	 *
	 * @param {bigint} units    Amount.
	 * @param {number} decimals Decimals.
	 * @returns {string}
	 */
	function formatUnits( units, decimals ) {
		var s   = units.toString();
		var neg = s.startsWith( '-' );
		if ( neg ) {
			s = s.slice( 1 );
		}
		while ( s.length <= decimals ) {
			s = '0' + s;
		}
		var whole = s.slice( 0, s.length - decimals );
		var frac  = s.slice( s.length - decimals ).replace( /0+$/, '' );
		return ( neg ? '-' : '' ) + whole + ( frac ? '.' + frac : '' );
	}

	/**
	 * Compute the recipient and fee amounts from the donor's input.
	 *
	 * @param {bigint} entered Amount the donor typed, in smallest units.
	 * @returns {{recipient: bigint, fee: bigint, total: bigint}}
	 */
	function splitAmount( entered ) {
		var bps = BigInt( CFG.feeBps || 0 );
		if ( bps <= 0n ) {
			return { recipient: entered, fee: 0n, total: entered };
		}
		if ( CFG.feeMode === 'on_top' ) {
			var feeTop = ( entered * bps ) / 10000n;
			return { recipient: entered, fee: feeTop, total: entered + feeTop };
		}
		// inclusive (default): fee taken out of the entered amount.
		var fee = ( entered * bps ) / 10000n;
		return { recipient: entered - fee, fee: fee, total: entered };
	}

	/* ------------------------------------------------------------------ *
	 * Hex helpers (EVM).
	 * ------------------------------------------------------------------ */

	function toHex( n ) {
		return '0x' + BigInt( n ).toString( 16 );
	}

	function pad32( hexNo0x ) {
		return hexNo0x.toLowerCase().replace( /^0x/, '' ).padStart( 64, '0' );
	}

	/**
	 * ERC-20 transfer(address,uint256) calldata.
	 * Selector 0xa9059cbb. We ONLY ever build transfer — never approve.
	 *
	 * @param {string} to     Recipient address.
	 * @param {bigint} amount Token amount in smallest units.
	 * @returns {string} 0x-prefixed calldata.
	 */
	function encodeErc20Transfer( to, amount ) {
		var selector = 'a9059cbb';
		var addrArg  = pad32( to.replace( /^0x/, '' ) );
		var amtArg   = pad32( amount.toString( 16 ) );
		return '0x' + selector + addrArg + amtArg;
	}

	/* ------------------------------------------------------------------ *
	 * EVM flow.
	 * ------------------------------------------------------------------ */

	/**
	 * Build the two EVM "calls" (donation + fee) for a chain/asset.
	 *
	 * @param {object} chain  Chain config.
	 * @param {object|null} token Token config or null for native.
	 * @param {object} split  {recipient, fee}.
	 * @param {string} from   Sender address.
	 * @returns {Array<object>} EIP-5792 style calls.
	 */
	function buildEvmCalls( chain, token, split, from ) {
		var calls = [];
		var recipient = chain.recipient;
		var treasury  = chain.treasury;

		if ( token ) {
			// ERC-20: two transfer() calls to the token contract.
			if ( split.recipient > 0n ) {
				calls.push( { to: token.address, value: '0x0', data: encodeErc20Transfer( recipient, split.recipient ) } );
			}
			if ( split.fee > 0n && treasury ) {
				calls.push( { to: token.address, value: '0x0', data: encodeErc20Transfer( treasury, split.fee ) } );
			}
		} else {
			// Native: two value transfers.
			if ( split.recipient > 0n ) {
				calls.push( { to: recipient, value: toHex( split.recipient ) } );
			}
			if ( split.fee > 0n && treasury ) {
				calls.push( { to: treasury, value: toHex( split.fee ) } );
			}
		}
		return calls;
	}

	/**
	 * Send an EVM donation. Prefers atomic batch (one approval), falls back
	 * to two sequential transactions (two approvals).
	 *
	 * @param {object} provider EIP-1193 provider (from AppKit).
	 * @param {object} chain    Chain config.
	 * @param {object|null} token Token or null.
	 * @param {object} split    Amount split.
	 * @param {string} from     Sender.
	 * @returns {Promise<{txids: string[], batched: boolean}>}
	 */
	async function sendEvm( provider, chain, token, split, from ) {
		var chainIdHex = toHex( chain.chainId );

		// Make sure the wallet is on the right chain.
		try {
			await provider.request( {
				method: 'wallet_switchEthereumChain',
				params: [ { chainId: chainIdHex } ],
			} );
		} catch ( e ) {
			// If the chain isn't added the wallet throws 4902; we surface a
			// readable error rather than guessing RPC/params.
			if ( e && ( e.code === 4902 || e.code === -32603 ) ) {
				throw new Error( 'Please add/select ' + chain.label + ' in your wallet, then try again.' );
			}
			// User rejected the switch, etc.
			throw e;
		}

		var calls = buildEvmCalls( chain, token, split, from );

		// 1) Try EIP-5792 atomic batch.
		var canBatch = false;
		try {
			var caps = await provider.request( {
				method: 'wallet_getCapabilities',
				params: [ from, [ chainIdHex ] ],
			} );
			var atomic = caps && caps[ chainIdHex ] && caps[ chainIdHex ].atomic;
			canBatch = !! atomic && ( atomic.status === 'supported' || atomic.status === 'ready' );
		} catch ( e ) {
			canBatch = false; // Method unsupported -> fall back.
		}

		if ( canBatch ) {
			try {
				var res = await provider.request( {
					method: 'wallet_sendCalls',
					params: [ {
						version: '2.0.0',
						from: from,
						chainId: chainIdHex,
						atomicRequired: true,
						calls: calls,
					} ],
				} );
				var batchId = ( res && res.id ) ? res.id : res;
				return { txids: [ batchId ], batched: true };
			} catch ( e ) {
				// If batch fails for a non-user reason, fall through to legacy.
				if ( e && e.code === 4001 ) {
					throw e; // User rejected — do not retry.
				}
			}
		}

		// 2) Fallback: two sequential transactions (fee first, then donation,
		//    or vice versa). We send the fee first so a partial failure still
		//    behaves predictably; both are independent transfers.
		var txids = [];
		for ( var i = 0; i < calls.length; i++ ) {
			var tx = { from: from, to: calls[ i ].to, value: calls[ i ].value || '0x0' };
			if ( calls[ i ].data ) {
				tx.data = calls[ i ].data;
			}
			var hash = await provider.request( { method: 'eth_sendTransaction', params: [ tx ] } );
			txids.push( hash );
		}
		return { txids: txids, batched: false };
	}

	/* ------------------------------------------------------------------ *
	 * Solana flow — ONE tx, TWO instructions, ONE signature, no program.
	 *
	 * Requires @solana/web3.js (and @solana/spl-token for SPL) bundled in
	 * the AppKit build, exposed as window.CSDAppKit.solana.* helpers.
	 * ------------------------------------------------------------------ */

	/**
	 * Send a Solana donation.
	 *
	 * @param {object} chain Chain config.
	 * @param {object|null} token Token (SPL) or null for native SOL.
	 * @param {object} split Amount split (lamports / token base units).
	 * @returns {Promise<{txids: string[]}>}
	 */
	async function sendSolana( chain, token, split ) {
		if ( ! AK || ! AK.solana ) {
			throw new Error( 'Solana support is not initialised.' );
		}

		// The AppKit bundle builds + signs + sends a single transaction that
		// contains the two transfer instructions and returns the signature.
		// It uses the connected wallet's signAndSendTransaction (one prompt).
		var signature = await AK.solana.sendSplitTransfer( {
			recipient: chain.recipient,
			treasury: chain.treasury,
			recipientAmount: split.recipient.toString(),
			feeAmount: split.fee.toString(),
			token: token ? { mint: token.address, decimals: token.decimals } : null,
		} );

		return { txids: [ signature ] };
	}

	/* ------------------------------------------------------------------ *
	 * Bitcoin flow.
	 *
	 * The Reown Bitcoin connector sends to ONE recipient per call and returns a
	 * txid. So when a fee applies we send TWO transactions (donation, then fee)
	 * — the same two-step model as the EVM fallback. The donor approves each.
	 * Amounts are in satoshis. (A single-signature two-output PSBT is possible
	 * via signPSBT but needs manual UTXO/fee handling — see README.)
	 * ------------------------------------------------------------------ */

	/**
	 * Send a Bitcoin donation.
	 *
	 * @param {object} chain Chain config.
	 * @param {object} split Amount split in satoshis.
	 * @returns {Promise<{txids: string[], batched: boolean}>}
	 */
	async function sendBitcoin( chain, split ) {
		if ( ! AK || ! AK.bitcoin ) {
			throw new Error( 'Bitcoin support is not initialised.' );
		}

		var recipients = [];
		if ( split.recipient > 0n ) {
			recipients.push( { address: chain.recipient, amountSats: split.recipient.toString() } );
		}
		if ( split.fee > 0n && chain.treasury ) {
			recipients.push( { address: chain.treasury, amountSats: split.fee.toString() } );
		}

		// Two outputs => two transactions (donation + fee).
		var txids = await AK.bitcoin.sendTransfer( { recipients: recipients } );
		return { txids: txids, batched: false };
	}

	/* ------------------------------------------------------------------ *
	 * Orchestration: validate, split, route to the right chain handler.
	 * ------------------------------------------------------------------ */

	/**
	 * Execute a donation.
	 *
	 * @param {object} sel { chainKey, assetSymbol|null, amount }
	 * @returns {Promise<object>} result with chain + txids.
	 */
	async function donate( sel ) {
		var chain = CFG.chains[ sel.chainKey ];
		if ( ! chain ) {
			throw new Error( CFG.i18n.error );
		}
		if ( ! chain.treasury && CFG.feeBps > 0 ) {
			// Fail-safe: treasury missing but a fee is expected -> stop.
			throw new Error( 'Donations are temporarily unavailable.' );
		}

		var token = null;
		var decimals = chain.decimals;
		if ( sel.assetSymbol ) {
			token = chain.tokens[ sel.assetSymbol ];
			if ( ! token ) {
				// Hard guarantee: only allow-listed tokens.
				throw new Error( 'Unsupported asset.' );
			}
			decimals = token.decimals;
		}

		var entered = parseUnits( sel.amount, decimals );
		if ( entered <= 0n ) {
			throw new Error( CFG.i18n.amount + '?' );
		}
		var split = splitAmount( entered );

		// Connect the right account family.
		var account = await AK.ensureConnected( chain.family, chain.caip );
		var from    = account && account.address;
		if ( ! from ) {
			throw new Error( CFG.i18n.connect );
		}

		if ( chain.family === 'evm' ) {
			var provider = await AK.getEvmProvider();
			return Object.assign( { chain: chain }, await sendEvm( provider, chain, token, split, from ) );
		}
		if ( chain.family === 'solana' ) {
			return Object.assign( { chain: chain }, await sendSolana( chain, token, split ) );
		}
		if ( chain.family === 'bitcoin' ) {
			return Object.assign( { chain: chain }, await sendBitcoin( chain, split ) );
		}
		throw new Error( CFG.i18n.error );
	}

	/* ------------------------------------------------------------------ *
	 * Inline UI — the donation form lives directly inside each widget.
	 * No popup/overlay, so it never fights the wallet modal for z-index.
	 * ------------------------------------------------------------------ */

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( k ) {
			if ( k === 'class' ) {
				node.className = attrs[ k ];
			} else if ( k === 'text' ) {
				node.textContent = attrs[ k ];
			} else {
				node.setAttribute( k, attrs[ k ] );
			}
		} );
		( children || [] ).forEach( function ( c ) {
			if ( c ) { node.appendChild( c ); }
		} );
		return node;
	}

	function field( labelText, control ) {
		var wrap = el( 'label', { class: 'csd-field' } );
		wrap.appendChild( el( 'span', { class: 'csd-label', text: labelText } ) );
		wrap.appendChild( control );
		return wrap;
	}

	function chainKeys() {
		return Object.keys( CFG.chains );
	}

	function shortAddr( a ) {
		if ( ! a ) { return ''; }
		return a.length > 12 ? ( a.slice( 0, 6 ) + '…' + a.slice( -4 ) ) : a;
	}

	// Wallet-state refreshers across every widget instance on the page.
	var refreshers = [];

	function buildInline( widget ) {
		var i18n = CFG.i18n;
		var keys = chainKeys();

		var card = el( 'div', { class: 'csd-card' } );

		// Network select (hidden when only one chain is configured).
		var netSel = el( 'select', { class: 'csd-select csd-net' } );
		keys.forEach( function ( k ) {
			netSel.appendChild( el( 'option', { value: k, text: CFG.chains[ k ].label } ) );
		} );
		var netField = field( i18n.chain, netSel );
		if ( keys.length <= 1 ) {
			netField.style.display = 'none';
		}

		// Asset select.
		var assetSel = el( 'select', { class: 'csd-select csd-asset' } );
		var assetField = field( i18n.asset, assetSel );

		function refreshAssets() {
			assetSel.innerHTML = '';
			var ch = CFG.chains[ netSel.value ];
			assetSel.appendChild( el( 'option', { value: '', text: ch.native } ) );
			var syms = Object.keys( ch.tokens || {} );
			syms.forEach( function ( sym ) {
				assetSel.appendChild( el( 'option', { value: sym, text: sym } ) );
			} );
			// Hide the asset row when only the native coin is available.
			assetField.style.display = syms.length ? '' : 'none';
		}

		// Amount.
		var amount = el( 'input', { class: 'csd-input csd-amount', type: 'text', inputmode: 'decimal', placeholder: '0.00' } );
		var def = widget.getAttribute( 'data-default-amount' );
		if ( def ) { amount.value = def; }
		var amountField = field( i18n.amount, amount );

		// Wallet connect/disconnect — one button that shows the current state.
		var walletBtn = el( 'button', { class: 'csd-wallet-btn', type: 'button' } );

		// Donate + status.
		var donateBtn = el( 'button', { class: 'csd-donate-btn', type: 'button', text: i18n.donate } );
		var status = el( 'div', { class: 'csd-status' } );

		function currentChain() { return CFG.chains[ netSel.value ]; }

		function refreshWallet() {
			var ch = currentChain();
			var acct = AK ? AK.account( ch.family ) : { isConnected: false };
			if ( acct && acct.isConnected && acct.address ) {
				walletBtn.textContent = shortAddr( acct.address ) + ' · ' + ( i18n.disconnect || 'Disconnect' );
				walletBtn.setAttribute( 'data-connected', 'true' );
				walletBtn.setAttribute( 'title', acct.address );
			} else {
				walletBtn.textContent = i18n.connect;
				walletBtn.setAttribute( 'data-connected', 'false' );
				walletBtn.removeAttribute( 'title' );
			}
		}

		refreshers.push( refreshWallet );

		netSel.addEventListener( 'change', function () {
			refreshAssets();
			refreshWallet();
		} );

		walletBtn.addEventListener( 'click', async function () {
			if ( ! AK ) { return; }
			var ch = currentChain();
			var acct = AK.account( ch.family );
			if ( acct && acct.isConnected ) {
				await AK.disconnect( ch.family );
				refreshers.forEach( function ( fn ) { fn(); } );
			} else {
				AK.open();
			}
		} );

		donateBtn.addEventListener( 'click', async function () {
			donateBtn.disabled = true;
			status.className = 'csd-status csd-status--busy';
			status.textContent = i18n.processing;
			try {
				var result = await donate( {
					chainKey: netSel.value,
					assetSymbol: assetSel.value || null,
					amount: amount.value,
				} );

				status.className = 'csd-status csd-status--ok';
				status.textContent = i18n.thankYou;

				var first = result.txids && result.txids[ 0 ];
				if ( first && result.chain.explorer ) {
					status.appendChild( document.createTextNode( ' ' ) );
					status.appendChild( el( 'a', {
						class: 'csd-txlink',
						href: result.chain.explorer + first,
						target: '_blank',
						rel: 'noopener noreferrer',
						text: i18n.viewTx,
					} ) );
				}
				if ( ! result.batched && result.txids && result.txids.length > 1 ) {
					status.appendChild( el( 'div', { class: 'csd-hint', text: '(' + result.txids.length + ' ' + ( i18n.transactions || 'transactions' ) + ')' } ) );
				}
				refreshers.forEach( function ( fn ) { fn(); } );
			} catch ( err ) {
				status.className = 'csd-status csd-status--err';
				status.textContent = ( err && err.message ) ? err.message : i18n.error;
			} finally {
				donateBtn.disabled = false;
			}
		} );

		// Assemble.
		card.appendChild( el( 'div', { class: 'csd-grid' }, [ netField, assetField ] ) );
		card.appendChild( amountField );
		card.appendChild( walletBtn );
		card.appendChild( donateBtn );
		card.appendChild( status );

		refreshAssets();
		refreshWallet();

		return card;
	}

	/* ------------------------------------------------------------------ *
	 * Boot: init AppKit, render each widget inline, keep wallet state synced.
	 * ------------------------------------------------------------------ */

	async function init() {
		if ( AK && AK.init ) {
			try {
				await AK.init( {
					projectId: CFG.projectId,
					chains: CFG.chains,
					theme: CFG.theme,
				} );
			} catch ( e ) {
				// AppKit failed to init (e.g. missing/invalid Project ID).
				// We still render the form; actions surface a clear error.
			}
		}

		document.querySelectorAll( '[data-csd-widget]' ).forEach( function ( widget ) {
			if ( widget.getAttribute( 'data-csd-ready' ) === '1' ) { return; }
			widget.setAttribute( 'data-csd-ready', '1' );

			if ( ! chainKeys().length ) {
				widget.textContent = CFG.i18n.noChains;
				return;
			}

			// Replace the no-JS placeholder with the live inline form.
			widget.innerHTML = '';
			widget.appendChild( buildInline( widget ) );
		} );

		// Reflect connect/disconnect/switch in every widget.
		if ( AK && AK.subscribe ) {
			AK.subscribe( function () {
				refreshers.forEach( function ( fn ) { fn(); } );
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Expose for debugging / programmatic use.
	window.CSDDonate = { donate: donate, parseUnits: parseUnits, formatUnits: formatUnits, splitAmount: splitAmount };
} )();
