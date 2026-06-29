/**
 * CSDAppKit — thin wrapper around Reown AppKit (WalletConnect) implementing
 * the contract the donation engine expects:
 *
 *   window.CSDAppKit = {
 *     init({ projectId, chains, theme }): Promise<void>,
 *     open(): void,
 *     ensureConnected(family, caip): Promise<{ address }>,
 *     getEvmProvider(): Promise<EIP1193Provider>,
 *     solana:  { sendSplitTransfer(opts): Promise<signature> },
 *     bitcoin: { sendTransfer({ recipients }): Promise<txid> },
 *   }
 *
 * BUILD: this is SOURCE. Bundle it to /build/appkit-bundle.js (see README).
 * WordPress.org forbids loading executable JS from a CDN, so AppKit must be
 * vendored/bundled locally.
 *
 * The AppKit calls used here (getProvider/getAccount/switchNetwork/
 * subscribeAccount, the Solana provider's signAndSendTransaction, and the
 * Bitcoin connector's single-recipient sendTransfer) were checked against
 * @reown/appkit 1.8.21. If you bump the major version, re-verify these.
 * Wallet-side behaviour (EVM batch support, confirmation UX) varies per wallet
 * and is feature-detected at runtime.
 */

import { createAppKit } from '@reown/appkit';
import { EthersAdapter } from '@reown/appkit-adapter-ethers';
import { SolanaAdapter } from '@reown/appkit-adapter-solana';
import { BitcoinAdapter } from '@reown/appkit-adapter-bitcoin';
import {
	mainnet, polygon, base, bsc, solana,
	bitcoin as bitcoinNet,
} from '@reown/appkit/networks';

// Solana transaction building (no custom program needed).
import {
	Connection, PublicKey, Transaction, SystemProgram,
} from '@solana/web3.js';
import {
	getAssociatedTokenAddress,
	createTransferCheckedInstruction,
	createAssociatedTokenAccountInstruction,
	TOKEN_PROGRAM_ID,
} from '@solana/spl-token';

let appkit = null;
let solanaConnection = null;

const NETWORKS = [ mainnet, polygon, base, bsc, solana, bitcoinNet ];

const CSDAppKit = {

	async init( { projectId, theme } ) {
		if ( appkit ) {
			return;
		}
		if ( ! projectId ) {
			throw new Error( 'Missing WalletConnect/Reown Project ID.' );
		}

		appkit = createAppKit( {
			adapters: [
				new EthersAdapter(),
				new SolanaAdapter(),
				new BitcoinAdapter(),
			],
			networks: NETWORKS,
			projectId,
			themeMode: theme === 'auto' ? undefined : theme,
			features: {
				analytics: false,
				email: false,
				socials: false,
				swaps: false,
				onramp: false,
				history: false,
			},
			enableWalletConnect: true,
			metadata: {
				name: 'CryptoStack Donations',
				description: 'Multi-chain crypto donations',
				url: window.location.origin,
				icons: [],
			},
		} );

		// A public RPC is fine for building/sending; replace with your own
		// endpoint for production reliability.
		solanaConnection = new Connection(
			'https://api.mainnet-beta.solana.com',
			'confirmed'
		);
	},

	open() {
		if ( appkit ) {
			appkit.open();
		}
	},

	/** Map our family name to the AppKit chain namespace. */
	namespaceFor( family ) {
		return family === 'evm' ? 'eip155'
			: family === 'solana' ? 'solana'
			: 'bip122';
	},

	/**
	 * Current account for a family: { address, isConnected }.
	 */
	account( family ) {
		if ( ! appkit ) {
			return { address: undefined, isConnected: false };
		}
		const a = appkit.getAccount( this.namespaceFor( family ) ) || {};
		return { address: a.address, isConnected: !! a.isConnected };
	},

	/**
	 * Subscribe to account changes across all three namespaces. The callback
	 * fires whenever any wallet connects/disconnects/switches. Returns an
	 * unsubscribe function.
	 */
	subscribe( cb ) {
		if ( ! appkit || typeof appkit.subscribeAccount !== 'function' ) {
			return function () {};
		}
		const unsubs = [ 'eip155', 'solana', 'bip122' ].map( function ( ns ) {
			try {
				return appkit.subscribeAccount( function () { cb(); }, ns );
			} catch ( e ) {
				return function () {};
			}
		} );
		return function () {
			unsubs.forEach( function ( u ) {
				if ( typeof u === 'function' ) { u(); }
			} );
		};
	},

	/** Disconnect a family's wallet (or the active one). */
	async disconnect( family ) {
		if ( ! appkit ) {
			return;
		}
		try {
			await appkit.disconnect( family ? this.namespaceFor( family ) : undefined );
		} catch ( e ) { /* best effort */ }
	},

	/**
	 * Switch the active network for a family to the given CAIP id.
	 */
	async switchTo( caip ) {
		if ( ! appkit ) {
			return;
		}
		try {
			await appkit.switchNetwork( caipToNetwork( caip ) );
		} catch ( e ) { /* best effort */ }
	},

	/**
	 * Ensure a wallet of the given family is connected; open the modal and
	 * wait if not. Returns the active account.
	 */
	async ensureConnected( family, caip ) {
		const namespace = this.namespaceFor( family );

		// Verified for @reown/appkit 1.8.x: getAccount(namespace) -> { isConnected, address }.
		let account = appkit.getAccount( namespace );
		if ( account && account.isConnected && account.address ) {
			// Switch to the requested network if needed.
			try {
				await appkit.switchNetwork( caipToNetwork( caip ) );
			} catch ( e ) { /* best effort */ }
			return { address: account.address };
		}

		// Not connected: open modal and await connection.
		appkit.open();
		account = await waitForConnection( namespace );
		return { address: account.address };
	},

	/**
	 * Return the active EIP-1193 provider for EVM. Stable interface; the
	 * engine drives it with eth_sendTransaction / wallet_sendCalls /
	 * wallet_getCapabilities.
	 */
	async getEvmProvider() {
		// Verified for 1.8.x: getProvider('eip155') returns the EIP-1193 provider.
		const provider = appkit.getProvider( 'eip155' );
		if ( ! provider ) {
			throw new Error( 'No EVM wallet connected.' );
		}
		return provider;
	},

	solana: {
		/**
		 * Build ONE transaction containing TWO transfers (recipient + fee)
		 * and have the connected wallet sign+send it once. No program.
		 */
		async sendSplitTransfer( { recipient, treasury, recipientAmount, feeAmount, token } ) {
			const walletProvider = appkit.getProvider( 'solana' ); // Verified for 1.8.x.
			const fromStr = appkit.getAccount( "solana" )?.address;
			if ( ! walletProvider || ! fromStr ) {
				throw new Error( 'No Solana wallet connected.' );
			}

			const from = new PublicKey( fromStr );
			const to   = new PublicKey( recipient );
			const fee  = treasury ? new PublicKey( treasury ) : null;
			const tx   = new Transaction();

			if ( ! token ) {
				// Native SOL: two SystemProgram.transfer instructions.
				if ( BigInt( recipientAmount ) > 0n ) {
					tx.add( SystemProgram.transfer( {
						fromPubkey: from,
						toPubkey: to,
						lamports: BigInt( recipientAmount ),
					} ) );
				}
				if ( fee && BigInt( feeAmount ) > 0n ) {
					tx.add( SystemProgram.transfer( {
						fromPubkey: from,
						toPubkey: fee,
						lamports: BigInt( feeAmount ),
					} ) );
				}
			} else {
				// SPL token: transferChecked to recipient and treasury ATAs.
				const mint = new PublicKey( token.mint );
				const fromAta = await getAssociatedTokenAddress( mint, from );

				const addTokenTransfer = async ( ownerPk, amount ) => {
					if ( BigInt( amount ) <= 0n ) {
						return;
					}
					const destAta = await getAssociatedTokenAddress( mint, ownerPk );
					// Create the destination ATA if it doesn't exist (payer = donor).
					const info = await solanaConnection.getAccountInfo( destAta );
					if ( ! info ) {
						tx.add( createAssociatedTokenAccountInstruction(
							from, destAta, ownerPk, mint
						) );
					}
					tx.add( createTransferCheckedInstruction(
						fromAta, mint, destAta, from,
						BigInt( amount ), token.decimals, [], TOKEN_PROGRAM_ID
					) );
				};

				await addTokenTransfer( to, recipientAmount );
				if ( fee ) {
					await addTokenTransfer( fee, feeAmount );
				}
			}

			const { blockhash, lastValidBlockHeight } =
				await solanaConnection.getLatestBlockhash( 'confirmed' );
			tx.recentBlockhash = blockhash;
			tx.feePayer = from;

			// Verified for 1.8.x: the Solana provider exposes signAndSendTransaction.
			// wallet adapters. Most expose signAndSendTransaction returning
			// { signature }.
			const result = await walletProvider.signAndSendTransaction( tx );
			const signature = result?.signature || result;

			await solanaConnection.confirmTransaction(
				{ signature, blockhash, lastValidBlockHeight },
				'confirmed'
			);
			return signature;
		},
	},

	bitcoin: {
		/**
		 * Bitcoin transfers via the Reown Bitcoin connector.
		 *
		 * Verified against @reown/appkit 1.8.x: the connector's sendTransfer
		 * accepts a SINGLE { recipient, amount } (amount in satoshis, as a
		 * string) and returns a txid string. To take the 1% fee we therefore
		 * send TWO transactions (donation + fee) — the same two-step model as
		 * the EVM fallback. The donor approves each in their wallet.
		 *
		 * A single-signature, two-output PSBT is possible via the connector's
		 * signPSBT method, but that requires manual UTXO selection and fee
		 * estimation (e.g. bitcoinjs-lib). See README for that upgrade path.
		 *
		 * @param {{recipients: Array<{address:string, amountSats:string}>}} params
		 * @returns {Promise<string[]>} One txid per output sent.
		 */
		async sendTransfer( { recipients } ) {
			const provider = appkit.getProvider( 'bip122' );
			if ( ! provider || typeof provider.sendTransfer !== 'function' ) {
				throw new Error( 'No Bitcoin wallet connected.' );
			}

			const txids = [];
			for ( const r of recipients ) {
				const res = await provider.sendTransfer( {
					recipient: r.address,
					amount: String( r.amountSats ), // satoshis
				} );
				txids.push( ( res && res.txid ) ? res.txid : res );
			}
			return txids;
		},
	},
};

/* --------------------------------------------------------------------- */

function caipToNetwork( caip ) {
	return NETWORKS.find( ( n ) => n.caipNetworkId === caip || n.id?.toString() === caip ) || undefined;
}

function waitForConnection( namespace, timeoutMs = 120000 ) {
	return new Promise( ( resolve, reject ) => {
		const started = Date.now();
		// Verified for 1.8.x: subscribeAccount(cb, namespace) -> unsubscribe fn.
		const unsub = appkit.subscribeAccount?.( ( acct ) => {
			if ( acct && acct.isConnected && acct.address ) {
				cleanup();
				resolve( { address: acct.address } );
			}
		}, namespace );

		const poll = setInterval( () => {
			const acct = appkit.getAccount( namespace );
			if ( acct && acct.isConnected && acct.address ) {
				cleanup();
				resolve( { address: acct.address } );
			} else if ( Date.now() - started > timeoutMs ) {
				cleanup();
				reject( new Error( 'Wallet connection timed out.' ) );
			}
		}, 500 );

		function cleanup() {
			clearInterval( poll );
			if ( typeof unsub === 'function' ) {
				unsub();
			}
		}
	} );
}

window.CSDAppKit = CSDAppKit;
export default CSDAppKit;
